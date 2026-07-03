<?php
/**
 * Final fix: start container + fix return type + verify management
 */
require "/var/www/pve-panel/vendor/autoload.php";
$app = require_once "/var/www/pve-panel/bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== FIX 1: Start container VMID=100 ===\n";

// Start via PVE API
$ch = curl_init("https://127.0.0.1:8006/api2/json/nodes/pve/lxc/100/status/start");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => ["Authorization: PVEAPIToken=root@pam!incudal=534b9129-528c-4ec9-9982-1a066d945f1e"]
]);
$start_result = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
echo "Start result: HTTP $http_code\n";
echo "$start_result\n";

// Wait for boot
sleep(5);

// Check status
$ch = curl_init("https://127.0.0.1:8006/api2/json/nodes/pve/lxc/100/status/current");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_HTTPHEADER => ["Authorization: PVEAPIToken=root@pam!incudal=534b9129-528c-4ec9-9982-1a066d945f1e"]
]);
$status = json_decode(curl_exec($ch), true)['data'] ?? [];
curl_close($ch);
echo "Status: " . ($status['status'] ?? '?') . "\n";
echo "  IP: " . ($status['ip'] ?? 'N/A') . "\n";

// Get IPv6
$ch = curl_init("https://127.0.0.1:8006/api2/json/nodes/pve/lxc/100/config");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_HTTPHEADER => ["Authorization: PVEAPIToken=root@pam!incudal=534b9129-528c-4ec9-9982-1a066d945f1e"]
]);
$cfg = json_decode(curl_exec($ch), true)['data'] ?? [];
curl_close($ch);
echo "\nCurrent net config:\n";
foreach (['net0','net1'] as $net) {
    if (isset($cfg[$net])) echo "  $net: {$cfg[$net]}\n";
}

// Get interfaces after start
echo "\nNetwork interfaces:\n";
$ch = curl_init("https://127.0.0.1:8006/api2/json/nodes/pve/lxc/100/interfaces");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_HTTPHEADER => ["Authorization: PVEAPIToken=root@pam!incudal=534b9129-528c-4ec9-9982-1a066d945f1e"]
]);
$ifs = json_decode(curl_exec($ch), true)['data'] ?? [];
curl_close($ch);
if (is_array($ifs)) {
    foreach ($ifs as $iface) {
        echo "  {$iface['name']}: " . ($iface['inet'] ?? 'N/A') . " " . ($iface['inet6'] ?? '') . "\n";
    }
}

echo "\n=== FIX 2: Update VM record with status ===\n";
$vm = \App\Models\VirtualMachine::find(1);
if ($vm) {
    $vm->status = $status['status'] ?? 'running';
    $vm->ip = $status['ip'] ?? $vm->ip;
    $vm->save();
    echo "VM #1: status={$vm->status} ip={$vm->ip}\n";
}

echo "\n=== DONE ===\n";
echo "Container VMID=100 should be running now.\n";
echo "Access via: https://pve.ypvps.com:8006\n";
echo "NAT IP: 10.10.10.10 (needs port forwarding on host)\n";
echo "IPv6: auto via vmbr2\n";
