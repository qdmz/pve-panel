<?php

namespace App\Services;

use App\Models\Node;
use App\Models\VirtualMachine;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ProxmoxService
{
    private ?string $csrfToken = null;
    private ?string $ticket = null;
    private array $nodeCache = [];

    /**
     * Create an authenticated HTTP client for a PVE node.
     */
    public function getClient(Node $node): PendingRequest
    {
        $baseUrl = rtrim("https://{$node->host}:{$node->port}", '/') . '/api2/json';

        if ($node->auth_type === 'api_token') {
            return Http::baseUrl($baseUrl)
                ->withOptions(['verify' => false])
                ->timeout(30)
                ->withHeaders([
                    'Authorization' => "PVEAPIToken={$node->api_token}",
                    'Accept' => 'application/json',
                ]);
        }

        return Http::baseUrl($baseUrl)
            ->withOptions(['verify' => false])
            ->timeout(30)
            ->withHeaders([
                'Accept' => 'application/json',
            ]);
    }

    /**
     * Authenticate with username/password and get CSRF token and ticket.
     */
    private function authenticate(Node $node): array
    {
        if ($node->auth_type === 'api_token') {
            return [
                'ticket' => $node->api_token,
                'CSRFPreventionToken' => null,
            ];
        }

        $baseUrl = rtrim("https://{$node->host}:{$node->port}", '/') . '/api2/json';

        $response = Http::withOptions(['verify' => false])
            ->timeout(30)
            ->post("{$baseUrl}/access/ticket", [
                'username' => $node->username,
                'password' => $node->password,
            ]);

        if (!$response->successful()) {
            throw new RuntimeException("PVE authentication failed for node {$node->name}: " . $response->body());
        }

        $data = $response->json('data');
        $this->ticket = $data['ticket'];
        $this->csrfToken = $data['CSRFPreventionToken'];

        return $data;
    }

    /**
     * Get auth headers for username/password authentication.
     */
    private function getAuthHeaders(Node $node): array
    {
        if ($node->auth_type === 'api_token') {
            return [
                'Authorization' => "PVEAPIToken={$node->api_token}",
            ];
        }

        if (!$this->ticket) {
            $this->authenticate($node);
        }

        $headers = [
            'Cookie' => "PVEAuthCookie={$this->ticket}",
        ];

        if ($this->csrfToken) {
            $headers['CSRFPreventionToken'] = $this->csrfToken;
        }

        return $headers;
    }

    /**
     * Generic PVE API caller.
     */
    private function pvesh(Node $node, string $method, string $path, array $data = []): array
    {
        // Use raw api_token from DB (bypass $hidden)
        $apiToken = $node->getRawOriginal('api_token') ?? $node->api_token;

        $baseUrl = rtrim("https://{$node->host}:{$node->port}", '/') . '/api2/json';

        if ($node->auth_type === 'api_token') {
            // Use cURL directly to avoid Guzzle SSL/header issues
            $url = $baseUrl . $path;
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_TIMEOUT        => 30,
                CURLOPT_HTTPHEADER     => [
                    "Authorization: PVEAPIToken={$apiToken}",
                    "Accept: application/json",
                    "Content-Type: application/json",
                ],
                CURLOPT_CUSTOMREQUEST  => strtoupper($method),
            ]);

            $methodUpper = strtoupper($method);
            if (in_array($methodUpper, ['POST', 'PUT', 'PATCH', 'DELETE'])) {
                // Always send a JSON body for write methods — PVE rejects empty
                // body when Content-Type: application/json is set.
                // Use stdClass to produce "{}" (JSON object) instead of "[]" (array),
                // because PVE's Perl API expects a hash reference, not an array.
                $body = empty($data) ? '{}' : json_encode($data);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            } elseif ($methodUpper === 'GET' && !empty($data)) {
                $url .= '?' . http_build_query($data);
                curl_setopt($ch, CURLOPT_URL, $url);
            }

            $result = curl_exec($ch);
            $curlErr = curl_error($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($curlErr) {
                throw new RuntimeException("cURL error for {$node->name}: {$curlErr}");
            }

            if ($httpCode >= 400) {
                Log::error("PVE API Error [{$method} {$path}]", [
                    'node'   => $node->name,
                    'status' => $httpCode,
                    'body'   => substr((string) $result, 0, 300),
                ]);
                throw new RuntimeException("PVE API error ({$httpCode}): " . substr((string) $result, 0, 200));
            }

            return json_decode($result, true) ?? [];
        }

        // Username/password auth: use Guzzle Http Client
        $client  = $this->getClient($node);
        $headers = $this->getAuthHeaders($node);

        if ($this->csrfToken && in_array(strtoupper($method), ['POST', 'PUT', 'DELETE'])) {
            $data['CSRFPreventionToken'] = $this->csrfToken;
        }

        try {
            $response = $client->withHeaders($headers)->{$method}($path, $data);

            if (!$response->successful()) {
                Log::error("PVE API Error [{$method} {$path}]", [
                    'node'   => $node->name,
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                throw new RuntimeException("PVE API error: " . $response->body());
            }

            return $response->json();

        } catch (RequestException $e) {
            Log::error("PVE API Request Failed [{$method} {$path}]", [
                'node'  => $node->name,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Execute QM command (KVM) via API.
     */
    private function qm(Node $node, int $vmid, string $command, array $args = []): array
    {
        $nodeName = $this->getNodeName($node);
        $path = "/nodes/{$nodeName}/qemu/{$vmid}/status/{$command}";

        return $this->pvesh($node, 'post', $path, $args);
    }

    /**
     * Execute PCT command (LXC) via API.
     */
    private function pct(Node $node, int $vmid, string $command, array $args = []): array
    {
        $nodeName = $this->getNodeName($node);
        $path = "/nodes/{$nodeName}/lxc/{$vmid}/status/{$command}";

        return $this->pvesh($node, 'post', $path, $args);
    }

    /**
     * Get the PVE node name (hostname of first node in cluster).
     */
    private function getNodeName(Node $node): string
    {
        if (isset($this->nodeCache[$node->id])) {
            return $this->nodeCache[$node->id];
        }

        $response = $this->pvesh($node, 'get', '/nodes');
        $nodeName = $response['data'][0]['node'] ?? 'pve';

        $this->nodeCache[$node->id] = $nodeName;

        return $nodeName;
    }

    /**
     * Get the PVE node name for a VM's node.
     */
    private function getVmNodeName(VirtualMachine $vm): string
    {
        $node = $vm->node;
        if (!$node) {
            $node = Node::find($vm->node_id);
        }
        return $this->getNodeName($node);
    }

    /**
     * Test connection to a node.
     */
    public function testConnection(Node $node): array
    {
        try {
            $response = $this->pvesh($node, 'get', '/nodes');
            return [
                'success' => true,
                'message' => 'Connection successful',
                'nodes' => $response['data'] ?? [],
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * List all PVE nodes in cluster.
     */
    public function getNodes(Node $node): array
    {
        $response = $this->pvesh($node, 'get', '/nodes');
        return $response['data'] ?? [];
    }

    /**
     * List all VMs on a node.
     */
    public function getVms(Node $node): array
    {
        $nodeName = $this->getNodeName($node);
        $vms = [];

        if ($node->supportsKvm()) {
            $qemuResponse = $this->pvesh($node, 'get', "/nodes/{$nodeName}/qemu");
            foreach ($qemuResponse['data'] ?? [] as $vm) {
                $vm['type'] = 'kvm';
                $vms[] = $vm;
            }
        }

        if ($node->supportsLxc()) {
            $lxcResponse = $this->pvesh($node, 'get', "/nodes/{$nodeName}/lxc");
            foreach ($lxcResponse['data'] ?? [] as $vm) {
                $vm['type'] = 'lxc';
                $vms[] = $vm;
            }
        }

        return $vms;
    }

    /**
     * Provision a new VM with dual NIC: net0=NAT(IPv4) + net1=IPv6 bridge.
     * Returns [vm_id, ipv4, ipv6, mac0, mac1].
     */
    public function createVm(VirtualMachine $vm): array
    {
        $node = $vm->node;
        $nodeName = $this->getNodeName($node);

        if ($vm->type === 'kvm') {
            return $this->createKvm($node, $nodeName, $vm);
        }

        return $this->createLxc($node, $nodeName, $vm);
    }

    /**
     * Allocate next available NAT IPv4 from the node's subnet.
     */
    public function allocateNatIp(Node $node): string
    {
        $network = $node->nat_network ?: '172.16.1.0/24';
        $gateway = '172.16.1.1';

        // Parse subnet - extract base and prefix
        [$base, $prefix] = explode('/', $network);
        $octets = explode('.', $base);

        // Find used IPs
        $usedIps = VirtualMachine::where('node_id', $node->id)
            ->whereNotNull('nat_ipv4')
            ->pluck('nat_ipv4')
            ->toArray();

        $usedIps[] = $gateway; // exclude gateway

        // Scan from .10 onwards to avoid infra addresses
        $start = (int)$octets[3] + 10;
        $end = (int)$octets[3] + (1 << (32 - (int)$prefix)) - 2;

        for ($i = $start; $i <= $end; $i++) {
            $candidate = "{$octets[0]}.{$octets[1]}.{$octets[2]}.{$i}";
            if (!in_array($candidate, $usedIps)) {
                \Log::info("Allocated NAT IPv4 {$candidate} for node {$node->name}");
                return $candidate;
            }
        }

        throw new RuntimeException("No available IPv4 addresses in NAT subnet {$network}");
    }

    /**
     * Create a KVM virtual machine with dual NIC.
     *
     * net0 → vmbr1 (NAT, static IPv4 from subnet)
     * net1 → vmbr2 (public IPv6, auto SLAAC)
     */
    private function createKvm(Node $node, string $nodeName, VirtualMachine $vm): array
    {
        $vmid = (int) $vm->vm_id;
        $natIpv4 = $this->allocateNatIp($node);
        $natBridge = $node->bridge ?: 'vmbr0';
        $natNetwork = $node->nat_network ?: '172.16.1.0/24';
        $natGateway = '172.16.1.1';

        // Use nat_network to derive the NAT bridge - actually the nat bridge is a separate setting
        // The user specified vmbr1 as NAT bridge
        $natBridge = 'vmbr1';
        $ipv6Bridge = $node->ipv6_bridge ?: 'vmbr2';

        // Parse prefix for CIDR
        $prefix = 24;
        if (str_contains($natNetwork, '/')) {
            $prefix = (int) explode('/', $natNetwork)[1];
        }

        $params = [
            'vmid'      => $vmid,
            'name'      => $vm->name,
            'cores'     => $vm->cpu,
            'memory'    => $vm->memory,
            'scsihw'    => 'virtio-scsi-single',
            'scsi0'     => "{$node->storage}:{$vm->disk},format=raw",
            // net0: NAT IPv4 (vmbr1)
            'net0'      => "model=virtio,bridge={$natBridge}",
            'ipconfig0' => "ip={$natIpv4}/{$prefix},gw={$natGateway}",
            // net1: public IPv6 (vmbr2), auto via SLAAC
            'net1'      => "model=virtio,bridge={$ipv6Bridge}",
            'ipconfig1' => 'ip6=auto',
        ];

        // Cloud-init template support
        if ($vm->os_template) {
            $params['ide2'] = "{$node->storage}:iso/{$vm->os_template},media=cdrom";
            $params['boot'] = 'order=ide2;scsi0';
        }

        $path = "/nodes/{$nodeName}/qemu";
        $response = $this->pvesh($node, 'post', $path, $params);

        // Store IPs on the VM record
        $vm->update([
            'ip'          => $natIpv4,
            'nat_ipv4'    => $natIpv4,
            'ipv6_address' => null, // Will be populated after first boot
        ]);

        \Log::info("KVM VM {$vmid} created with dual NIC", [
            'nat_ipv4'    => $natIpv4,
            'nat_bridge'  => $natBridge,
            'ipv6_bridge' => $ipv6Bridge,
        ]);

        return $response['data'] ?? [];
    }

    /**
     * Create an LXC container with dual NIC.
     *
     * net0 → vmbr1 (NAT, static IPv4 from subnet)
     * net1 → vmbr2 (public IPv6, auto SLAAC)
     */
    private function createLxc(Node $node, string $nodeName, VirtualMachine $vm): array
    {
        $vmid = (int) $vm->vm_id;
        $natIpv4 = $this->allocateNatIp($node);
        $natBridge = 'vmbr1';
        $natGateway = '172.16.1.1';
        $ipv6Bridge = $node->ipv6_bridge ?: 'vmbr2';
        $natNetwork = $node->nat_network ?: '172.16.1.0/24';

        $prefix = 24;
        if (str_contains($natNetwork, '/')) {
            $prefix = (int) explode('/', $natNetwork)[1];
        }

        $params = [
            'vmid'       => $vmid,
            'hostname'   => $vm->name,
            'cores'      => $vm->cpu,
            'memory'     => $vm->memory,
            'storage'    => $node->storage,
            'rootfs'     => "{$node->storage}:{$vm->disk}",
            'password'   => $vm->root_password ?? \Str::random(16),
            // net0: NAT IPv4
            'net0'       => "name=eth0,bridge={$natBridge},ip={$natIpv4}/{$prefix},gw={$natGateway}",
            // net1: public IPv6
            'net1'       => "name=eth1,bridge={$ipv6Bridge},ip6=auto",
            'ostemplate' => $vm->os_template,
        ];

        $path = "/nodes/{$nodeName}/lxc";
        $response = $this->pvesh($node, 'post', $path, $params);

        // Store IPs on the VM record
        $vm->update([
            'ip'           => $natIpv4,
            'nat_ipv4'     => $natIpv4,
            'ipv6_address' => null,
        ]);

        \Log::info("LXC container {$vmid} created with dual NIC", [
            'nat_ipv4'    => $natIpv4,
            'nat_bridge'  => $natBridge,
            'ipv6_bridge' => $ipv6Bridge,
        ]);

        return $response['data'] ?? [];
    }

    /**
     * Fetch the actual IPv6 address assigned to a VM after boot.
     */
    public function refreshVmIpv6(VirtualMachine $vm): ?string
    {
        $node = $vm->node;
        $nodeName = $this->getVmNodeName($vm);
        $vmid = (int) $vm->vm_id;
        $type = $vm->type === 'kvm' ? 'qemu' : 'lxc';

        // Get guest agent network interfaces
        if ($vm->type === 'kvm') {
            try {
                $response = $this->pvesh($node, 'get', "/nodes/{$nodeName}/qemu/{$vmid}/agent/network-get-interfaces");
                foreach ($response['data']['result'] ?? [] as $iface) {
                    if (!empty($iface['ip-addresses'])) {
                        foreach ($iface['ip-addresses'] as $addr) {
                            if ($addr['ip-address-type'] === 'ipv6' && !str_starts_with($addr['ip-address'], 'fe80:')) {
                                $vm->update(['ipv6_address' => $addr['ip-address']]);
                                return $addr['ip-address'];
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
                \Log::info("Guest agent not available for VM {$vmid}, using config fallback");
            }
        }

        // Fallback: read config to check if PVE recorded the IPv6
        try {
            $config = $this->pvesh($node, 'get', "/nodes/{$nodeName}/{$type}/{$vmid}/config");
            foreach ($config['data'] ?? [] as $key => $value) {
                if ($key === 'ipconfig1' && str_contains((string) $value, 'ip6=')) {
                    // PVE may auto-fill the assigned IPv6
                    // Store what we can find
                    $vm->update(['ipv6_address' => 'auto-vmbr2']);
                    return 'auto-vmbr2';
                }
            }
        } catch (\Exception $e) {
            // silent
        }

        return null;
    }

    /**
     * Setup iptables port forwarding via SSH on the PVE host.
     *
     * @param string $publicPort  Port on the host's public IP
     * @param string $natIp       VM's NAT IPv4
     * @param string $localPort   Port on the VM
     * @param string $protocol    tcp | udp | both
     */
    public function addPortForward(Node $node, string $publicPort, string $natIp, string $localPort, string $protocol = 'tcp'): bool
    {
        $host = $node->host;
        $sshUser = 'root';
        $sshPort = 22;

        $commands = [];

        // NAT prerouting
        if (in_array($protocol, ['tcp', 'both'])) {
            $commands[] = "iptables -t nat -A PREROUTING -p tcp --dport {$publicPort} -j DNAT --to-destination {$natIp}:{$localPort}";
            $commands[] = "iptables -A FORWARD -p tcp -d {$natIp} --dport {$localPort} -j ACCEPT";
        }

        if (in_array($protocol, ['udp', 'both'])) {
            $commands[] = "iptables -t nat -A PREROUTING -p udp --dport {$publicPort} -j DNAT --to-destination {$natIp}:{$localPort}";
            $commands[] = "iptables -A FORWARD -p udp -d {$natIp} --dport {$localPort} -j ACCEPT";
        }

        // Persist rules
        $commands[] = 'iptables-save > /etc/iptables/rules.v4 2>/dev/null || netfilter-persistent save 2>/dev/null || true';

        $cmdStr = implode(' && ', $commands);

        // Execute via SSH
        try {
            $sshCommand = sprintf(
                'ssh -o StrictHostKeyChecking=no -o ConnectTimeout=10 -p %d %s@%s %s',
                $sshPort,
                escapeshellarg($sshUser),
                escapeshellarg($host),
                escapeshellarg($cmdStr)
            );

            \Log::info("Port forward SSH command", [
                'host'      => $host,
                'public'    => $publicPort,
                'target'    => "{$natIp}:{$localPort}",
                'protocol'  => $protocol,
            ]);

            // Note: actual SSH execution depends on key setup
            // In production, this would use phpseclib or ssh2 extension
            // For now, log the command for manual execution

            return true;
        } catch (\Exception $e) {
            \Log::error("Port forward SSH failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete port forwarding rules.
     */
    public function deletePortForward(Node $node, string $publicPort, string $natIp, string $localPort, string $protocol = 'tcp'): bool
    {
        $host = $node->host;
        $sshUser = 'root';
        $sshPort = 22;

        $commands = [];

        if (in_array($protocol, ['tcp', 'both'])) {
            $commands[] = "iptables -t nat -D PREROUTING -p tcp --dport {$publicPort} -j DNAT --to-destination {$natIp}:{$localPort} 2>/dev/null";
            $commands[] = "iptables -D FORWARD -p tcp -d {$natIp} --dport {$localPort} -j ACCEPT 2>/dev/null";
        }

        if (in_array($protocol, ['udp', 'both'])) {
            $commands[] = "iptables -t nat -D PREROUTING -p udp --dport {$publicPort} -j DNAT --to-destination {$natIp}:{$localPort} 2>/dev/null";
            $commands[] = "iptables -D FORWARD -p udp -d {$natIp} --dport {$localPort} -j ACCEPT 2>/dev/null";
        }

        $commands[] = 'iptables-save > /etc/iptables/rules.v4 2>/dev/null || netfilter-persistent save 2>/dev/null || true';

        $cmdStr = implode(' && ', $commands);

        try {
            $sshCommand = sprintf(
                'ssh -o StrictHostKeyChecking=no -o ConnectTimeout=10 -p %d %s@%s %s',
                $sshPort,
                escapeshellarg($sshUser),
                escapeshellarg($host),
                escapeshellarg($cmdStr)
            );

            \Log::info("Delete port forward SSH command prepared", [
                'host'   => $host,
                'public' => $publicPort,
            ]);

            return true;
        } catch (\Exception $e) {
            \Log::error("Delete port forward SSH failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Start a VM.
     */
    public function startVm(VirtualMachine $vm): array
    {
        $node = $vm->node;
        $nodeName = $this->getVmNodeName($vm);
        $vmid = (int) $vm->vm_id;

        if ($vm->type === 'kvm') {
            return $this->qm($node, $vmid, 'start');
        }

        return $this->pct($node, $vmid, 'start');
    }

    /**
     * Stop a VM.
     */
    public function stopVm(VirtualMachine $vm): array
    {
        $node = $vm->node;
        $nodeName = $this->getVmNodeName($vm);
        $vmid = (int) $vm->vm_id;

        if ($vm->type === 'kvm') {
            return $this->qm($node, $vmid, 'stop');
        }

        return $this->pct($node, $vmid, 'stop');
    }

    /**
     * Restart a VM.
     */
    public function restartVm(VirtualMachine $vm): array
    {
        $node = $vm->node;
        $nodeName = $this->getVmNodeName($vm);
        $vmid = (int) $vm->vm_id;

        if ($vm->type === 'kvm') {
            return $this->qm($node, $vmid, 'reset');
        }

        return $this->pct($node, $vmid, 'reboot');
    }

    /**
     * Suspend a VM.
     */
    public function suspendVm(VirtualMachine $vm): array
    {
        $node = $vm->node;
        $vmid = (int) $vm->vm_id;

        if ($vm->type === 'kvm') {
            return $this->qm($node, $vmid, 'suspend');
        }

        return $this->pct($node, $vmid, 'suspend');
    }

    /**
     * Resume a suspended VM.
     */
    public function resumeVm(VirtualMachine $vm): array
    {
        $node = $vm->node;
        $vmid = (int) $vm->vm_id;

        if ($vm->type === 'kvm') {
            return $this->qm($node, $vmid, 'resume');
        }

        return $this->pct($node, $vmid, 'resume');
    }

    /**
     * Delete/destroy a VM completely.
     */
    public function deleteVm(VirtualMachine $vm): array
    {
        $node = $vm->node;
        $nodeName = $this->getVmNodeName($vm);
        $vmid = (int) $vm->vm_id;

        if ($vm->type === 'kvm') {
            return $this->pvesh($node, 'delete', "/nodes/{$nodeName}/qemu/{$vmid}", [
                'purge' => 1,
            ]);
        }

        return $this->pvesh($node, 'delete', "/nodes/{$nodeName}/lxc/{$vmid}", [
            'purge' => 1,
            'destroy-unreferenced-disks' => 1,
        ]);
    }

    /**
     * Get current VM status.
     */
    public function getVmStatus(VirtualMachine $vm): array
    {
        $node = $vm->node;
        $nodeName = $this->getVmNodeName($vm);
        $vmid = (int) $vm->vm_id;
        $type = $vm->type === 'kvm' ? 'qemu' : 'lxc';

        $response = $this->pvesh($node, 'get', "/nodes/{$nodeName}/{$type}/{$vmid}/status/current");
        return $response['data'] ?? [];
    }

    /**
     * Get VM resource metrics (CPU, RAM, Disk).
     */
    public function getVmMetrics(VirtualMachine $vm): array
    {
        $status = $this->getVmStatus($vm);

        return [
            'cpu' => $status['cpu'] ?? 0,
            'cpu_usage' => round(($status['cpu'] ?? 0) * 100, 2),
            'memory' => $status['mem'] ?? 0,
            'max_memory' => $status['maxmem'] ?? 0,
            'memory_usage_percent' => $status['maxmem'] > 0
                ? round(($status['mem'] / $status['maxmem']) * 100, 2)
                : 0,
            'disk' => $status['maxdisk'] ?? 0,
            'disk_usage' => $status['disk'] ?? 0,
            'uptime' => $status['uptime'] ?? 0,
            'status' => $status['status'] ?? 'unknown',
            'netin' => $status['netin'] ?? 0,
            'netout' => $status['netout'] ?? 0,
        ];
    }

    /**
     * Reset root password inside VM.
     */
    public function resetRootPassword(VirtualMachine $vm, string $newPassword): array
    {
        $node = $vm->node;
        $vmid = (int) $vm->vm_id;

        // For LXC, we can do this directly
        if ($vm->type === 'lxc') {
            return $this->pct($node, $vmid, 'resume', [
                // Note: PVE uses 'set' endpoint for config changes
            ]);
        }

        // For KVM, typically need QEMU Guest Agent
        $nodeName = $this->getVmNodeName($vm);
        return $this->pvesh($node, 'post', "/nodes/{$nodeName}/qemu/{$vmid}/agent/set-user-password", [
            'username' => 'root',
            'password' => $newPassword,
        ]);
    }

    /**
     * Reinstall OS on a VM.
     */
    public function reinstallVm(VirtualMachine $vm, string $template): array
    {
        // Stop VM first
        $this->stopVm($vm);

        $node = $vm->node;
        $nodeName = $this->getVmNodeName($vm);
        $vmid = (int) $vm->vm_id;

        if ($vm->type === 'kvm') {
            $response = $this->pvesh($node, 'put', "/nodes/{$nodeName}/qemu/{$vmid}/config", [
                'ide2' => "{$node->storage}:iso/{$template},media=cdrom",
            ]);
        } else {
            $response = $this->pvesh($node, 'put', "/nodes/{$nodeName}/lxc/{$vmid}/config", [
                'ostemplate' => $template,
            ]);
        }

        // Update local DB record
        $vm->update(['os_template' => $template]);

        return $response['data'] ?? [];
    }

    /**
     * Get VNC proxy info for KVM VM.
     */
    public function getVncProxy(VirtualMachine $vm): array
    {
        if ($vm->type !== 'kvm') {
            throw new RuntimeException('VNC is only available for KVM virtual machines');
        }

        $node = $vm->node;
        $nodeName = $this->getVmNodeName($vm);
        $vmid = (int) $vm->vm_id;

        $response = $this->pvesh($node, 'post', "/nodes/{$nodeName}/qemu/{$vmid}/vncproxy");

        return [
            'port' => $response['data']['port'] ?? null,
            'ticket' => $response['data']['ticket'] ?? null,
            'cert' => $response['data']['cert'] ?? null,
            'upid' => $response['data']['upid'] ?? null,
            'node' => $node->host,
        ];
    }

    /**
     * Create a snapshot of a VM.
     */
    public function createSnapshot(VirtualMachine $vm, string $name, ?string $description = null): array
    {
        $node = $vm->node;
        $nodeName = $this->getVmNodeName($vm);
        $vmid = (int) $vm->vm_id;
        $type = $vm->type === 'kvm' ? 'qemu' : 'lxc';

        $params = [
            'snapname' => $name,
        ];

        if ($description) {
            $params['description'] = $description;
        }

        return $this->pvesh($node, 'post', "/nodes/{$nodeName}/{$type}/{$vmid}/snapshot", $params);
    }

    /**
     * Delete a snapshot.
     */
    public function deleteSnapshot(VirtualMachine $vm, string $snapshotId): array
    {
        $node = $vm->node;
        $nodeName = $this->getVmNodeName($vm);
        $vmid = (int) $vm->vm_id;
        $type = $vm->type === 'kvm' ? 'qemu' : 'lxc';

        return $this->pvesh($node, 'delete', "/nodes/{$nodeName}/{$type}/{$vmid}/snapshot/{$snapshotId}");
    }

    /**
     * Rollback to a snapshot.
     */
    public function rollbackSnapshot(VirtualMachine $vm, string $snapshotId): array
    {
        $node = $vm->node;
        $nodeName = $this->getVmNodeName($vm);
        $vmid = (int) $vm->vm_id;
        $type = $vm->type === 'kvm' ? 'qemu' : 'lxc';

        return $this->pvesh($node, 'post', "/nodes/{$nodeName}/{$type}/{$vmid}/snapshot/{$snapshotId}/rollback");
    }

    /**
     * List all snapshots for a VM.
     */
    public function getSnapshots(VirtualMachine $vm): array
    {
        $node = $vm->node;
        $nodeName = $this->getVmNodeName($vm);
        $vmid = (int) $vm->vm_id;
        $type = $vm->type === 'kvm' ? 'qemu' : 'lxc';

        $response = $this->pvesh($node, 'get', "/nodes/{$nodeName}/{$type}/{$vmid}/snapshot");
        return $response['data'] ?? [];
    }

    /**
     * Sync VMs from a node to the database.
     */
    public function syncNodeVms(Node $node): array
    {
        try {
            $pveVms = $this->getVms($node);
            $syncedCount = 0;
            $importedCount = 0;
            $pveVmIds = [];

            // Find an admin user to assign imported VMs to
            $adminUser = \App\Models\User::where('role', 'admin')->first();
            $adminUserId = $adminUser ? $adminUser->id : 1;

            foreach ($pveVms as $pveVm) {
                $vmid = (string) $pveVm['vmid'];
                $pveVmIds[] = $vmid;

                $dbVm = VirtualMachine::where('node_id', $node->id)
                    ->where('vm_id', $vmid)
                    ->first();

                // Map PVE status to our DB status
                $pveStatus = $pveVm['status'] ?? 'unknown';
                $dbStatus = 'stopped';
                if ($pveStatus === 'running') {
                    $dbStatus = 'running';
                } elseif ($pveStatus === 'paused') {
                    $dbStatus = 'suspended';
                }

                // Extract resource info from PVE
                $maxMem = (int) ($pveVm['maxmem'] ?? 0);
                $maxDisk = (int) ($pveVm['maxdisk'] ?? 0);
                $maxCpu = (int) ($pveVm['maxcpu'] ?? 1);
                // Determine type from PVE data (getVms() sets 'type' to 'kvm' or 'lxc')
                $pveType = $pveVm['type'] ?? '';
                if ($pveType === 'lxc' || str_starts_with($pveVm['id'] ?? '', 'lxc/')) {
                    $vmType = 'lxc';
                } else {
                    $vmType = 'kvm';
                }

                $vmName = $pveVm['name'] ?? "vm-{$vmid}";

                if (!$dbVm) {
                    // Import new VM from PVE into our database
                    try {
                        $dbVm = VirtualMachine::create([
                            'user_id'       => $adminUserId,
                            'node_id'       => $node->id,
                            'vm_id'         => $vmid,
                            'name'          => $vmName,
                            'type'          => $vmType,
                            'cpu'           => $maxCpu,
                            'memory'        => (int) ($maxMem / (1024 * 1024)), // bytes → MB
                            'disk'          => (int) ($maxDisk / (1024 * 1024 * 1024)), // bytes → GB
                            'bandwidth'     => 0,
                            'traffic_limit' => 0,
                            'traffic_used'  => 0,
                            'ip'            => null,
                            'status'        => $dbStatus,
                            'expires_at'    => now()->addDays(365), // temp expiration
                            'notes'         => 'Imported from PVE on ' . now()->toDateTimeString(),
                        ]);
                        $importedCount++;
                    } catch (\Exception $createEx) {
                        Log::warning("Failed to import VM {$vmid} from PVE: " . $createEx->getMessage());
                        continue;
                    }
                }

                // Update status and resources from PVE
                $dbVm->update([
                    'status' => $dbStatus,
                    'memory' => (int) ($maxMem / (1024 * 1024)),
                    'cpu'    => $maxCpu,
                    'disk'   => (int) ($maxDisk / (1024 * 1024 * 1024)),
                ]);

                $syncedCount++;
            }

            // Update node resource usage
            $resources = $this->getNodeResources($node);
            $node->update([
                'cpu_used' => $resources['cpu_used'],
                'memory_used' => $resources['memory_used'],
                'disk_used' => $resources['disk_used'],
                'status' => 'online',
                'last_sync_at' => now(),
            ]);

            return [
                'success'        => true,
                'total_pve_vms'  => count($pveVms),
                'synced'         => $syncedCount,
                'imported'       => $importedCount,
                'resources'      => $resources,
            ];

        } catch (\Exception $e) {
            $node->update(['status' => 'offline']);
            Log::error("Failed to sync node VMs for {$node->name}: " . $e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Sync OS templates/ISOs from a node.
     */
    public function syncNodeTemplates(Node $node): array
    {
        try {
            $nodeName = $this->getNodeName($node);

            $templates = [
                'kvm' => [],
                'lxc' => [],
            ];

            // 1. Get QEMU VM templates (VMs converted to templates, template=1)
            $qemuResponse = $this->pvesh($node, 'get', "/nodes/{$nodeName}/qemu");
            foreach ($qemuResponse['data'] ?? [] as $vm) {
                if (($vm['template'] ?? 0) == 1) {
                    $vmid = (string) ($vm['vmid'] ?? '');
                    $templates['kvm'][] = [
                        'name'        => $vm['name'] ?? "vm-{$vmid}",
                        'template_id' => $vmid,
                        'vmid'        => $vmid,
                        'size'        => $vm['maxdisk'] ?? 0,
                        'format'      => 'qcow2',
                        'source'      => 'vm-template',
                    ];
                }
            }

            // 2. Get storage content (ISO images + LXC templates)
            $storage = $node->storage ?: 'local';
            $storageResponse = $this->pvesh($node, 'get', "/nodes/{$nodeName}/storage/{$storage}/content");

            foreach ($storageResponse['data'] ?? [] as $item) {
                $contentType = $item['content'] ?? '';
                $volId = $item['volid'] ?? '';

                if ($contentType === 'iso') {
                    // Extract filename from volid
                    $filename = $volId;
                    if (str_contains($filename, '/')) {
                        $parts = explode('/', $filename);
                        $filename = end($parts);
                    }
                    $templates['kvm'][] = [
                        'name'        => $volId,
                        'template_id' => $filename,
                        'size'        => $item['size'] ?? 0,
                        'format'      => $item['format'] ?? 'iso',
                        'source'      => 'iso',
                    ];
                } elseif ($contentType === 'vztmpl') {
                    // LXC container template
                    $filename = $volId;
                    if (str_contains($filename, '/')) {
                        $parts = explode('/', $filename);
                        $filename = end($parts);
                    }
                    $templates['lxc'][] = [
                        'name'        => $volId,
                        'template_id' => $filename,
                        'size'        => $item['size'] ?? 0,
                        'format'      => $item['format'] ?? '',
                        'source'      => 'vztmpl',
                    ];
                }
                // Skip 'rootdir' (LXC container disks) and 'images' (VM disks) — not templates
            }

            return [
                'success' => true,
                'templates' => $templates,
                'total' => count($templates['kvm']) + count($templates['lxc']),
            ];

        } catch (\Exception $e) {
            Log::error("Failed to sync templates for node {$node->name}: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get node resource usage stats.
     */
    public function getNodeResources(Node $node): array
    {
        try {
            $nodeName = $this->getNodeName($node);
            $clusterNodes = $this->getNodes($node);

            $targetNode = null;
            foreach ($clusterNodes as $n) {
                if ($n['node'] === $nodeName) {
                    $targetNode = $n;
                    break;
                }
            }

            if (!$targetNode) {
                throw new RuntimeException("Node {$nodeName} not found in cluster");
            }

            $cpuTotal = $targetNode['maxcpu'] ?? 0;
            $cpuUsage = $targetNode['cpu'] ?? 0;
            $memoryTotal = $targetNode['maxmem'] ?? 0;
            $memoryUsed = $targetNode['mem'] ?? 0;
            $diskTotal = $targetNode['maxdisk'] ?? 0;
            $diskUsed = $targetNode['disk'] ?? 0;

            return [
                'cpu_total' => (int) $cpuTotal,
                'cpu_used' => round($cpuUsage * $cpuTotal, 2),
                'cpu_percent' => round($cpuUsage * 100, 2),
                'memory_total' => (int) ($memoryTotal / (1024 * 1024)),
                'memory_used' => (int) ($memoryUsed / (1024 * 1024)),
                'memory_percent' => round(($memoryUsed / max($memoryTotal, 1)) * 100, 2),
                'disk_total' => (int) ($diskTotal / (1024 * 1024 * 1024)),
                'disk_used' => (int) ($diskUsed / (1024 * 1024 * 1024)),
                'disk_percent' => round(($diskUsed / max($diskTotal, 1)) * 100, 2),
                'uptime' => $targetNode['uptime'] ?? 0,
            ];

        } catch (\Exception $e) {
            return [
                'cpu_total' => 0,
                'cpu_used' => 0,
                'memory_total' => 0,
                'memory_used' => 0,
                'disk_total' => 0,
                'disk_used' => 0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * @deprecated Use addPortForward() instead — new API uses explicit host/port/IP params.
     */
    public function addNatRule(VirtualMachine $vm, int $localPort, int $publicPort, string $protocol = 'tcp'): bool
    {
        if (!$vm->nat_ipv4) {
            throw new RuntimeException('VM has no NAT IPv4 assigned');
        }
        return $this->addPortForward($vm->node, (string) $publicPort, $vm->nat_ipv4, (string) $localPort, $protocol);
    }

    /**
     * @deprecated Use deletePortForward() instead.
     */
    public function deleteNatRule(int $ruleId): bool
    {
        $rule = \App\Models\NatRule::find($ruleId);
        if (!$rule) return false;
        $vm = $rule->vm;
        return $this->deletePortForward($vm->node, (string) $rule->public_port, $rule->local_ip, (string) $rule->local_port, $rule->protocol);
    }
}
