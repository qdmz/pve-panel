<?php
/**
 * Fix Order::pay() + Create VM + Provision LXC
 */
require "/var/www/pve-panel/vendor/autoload.php";
$app = require_once "/var/www/pve-panel/bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "========================================\n";
echo "FIX 1: OrderController status -> payment_status\n";
echo "========================================\n";

// Fix the OrderController
$path = "/var/www/pve-panel/app/Http/Controllers/Api/OrderController.php";
$code = file_get_contents($path);
$code = str_replace(
    "\$order->status !== 'pending'",
    "\$order->payment_status !== 'pending'",
    $code,
    $count
);
if ($count > 0) {
    file_put_contents($path, $code);
    echo "[OK] Fixed $count occurrence(s) in OrderController\n";
} else {
    echo "[INFO] Already fixed or pattern not found\n";
}

echo "\n========================================\n";
echo "FIX 2: Create VM + Provision\n";
echo "========================================\n";

$order = \App\Models\Order::find(3);
echo "Order: #{$order->order_no} | payment_status={$order->payment_status} | product_id={$order->product_id}\n";

$node = \App\Models\Node::find(2);
$product = \App\Models\Product::find(9);
$user = \App\Models\User::find(9);

echo "Node: {$node->name} ({$node->host}:{$node->port})\n";
echo "Product: {$product->name} ({$product->type})\n";
echo "User: {$user->email}\n";

// Get next VMID from PVE
$ch = curl_init("https://127.0.0.1:8006/api2/json/cluster/nextid");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_HTTPHEADER => ["Authorization: PVEAPIToken=root@pam!incudal=534b9129-528c-4ec9-9982-1a066d945f1e"]
]);
$nextid_resp = json_decode(curl_exec($ch), true);
curl_close($ch);
$nextid = $nextid_resp['data'] ?? 100;
echo "PVE nextid: $nextid\n";

// Create VM record
$vm = \App\Models\VirtualMachine::create([
    'user_id' => 9,
    'node_id' => 2,
    'product_id' => 9,
    'order_id' => 3,
    'vm_id' => (string)$nextid,
    'name' => 'lxc-test-01',
    'type' => 'lxc',
    'cpu' => 1,
    'memory' => 512,
    'disk' => 1,
    'bandwidth' => 100,
    'traffic_limit' => 1000,
    'traffic_used' => 0,
    'os_template' => 'local:vztmpl/debian-12-standard_12.12-1_amd64.tar.zst',
    'root_password' => password_hash('Test@123456', PASSWORD_BCRYPT),
    'status' => 'creating',
    'expires_at' => now()->addMonth(),
    'next_due_date' => now()->addMonth()->toDateString(),
]);
echo "VM record: ID={$vm->id} vm_id={$vm->vm_id}\n";

// Link order to VM
$order->vm_id = $vm->id;
$order->save();
echo "Order linked to VM {$vm->id}\n";

// Try provision
echo "\n--- PROVISIONING ---\n";
try {
    $proxmox = new \App\Services\ProxmoxService();
    $result = $proxmox->createVm($vm);
    echo "[OK] Provision: " . json_encode($result) . "\n";
    
    // Get VM config from PVE
    $ch = curl_init("https://127.0.0.1:8006/api2/json/nodes/pve/lxc/{$nextid}/config");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER => ["Authorization: PVEAPIToken=root@pam!incudal=534b9129-528c-4ec9-9982-1a066d945f1e"]
    ]);
    $cfg = json_decode(curl_exec($ch), true)['data'] ?? [];
    curl_close($ch);
    echo "Container config:\n";
    foreach ($cfg as $k => $v) {
        echo "  $k: $v\n";
    }
    
    // Update VM status and IP
    $vm->status = 'running';
    if (!empty($cfg['net0'])) {
        // Extract IP from net0 if available
        if (preg_match('/ip=([\d.]+)/', $cfg['net0'], $m)) {
            $vm->ip = $m[1];
            echo "  IP detected: {$m[1]}\n";
        }
    }
    $vm->save();
    
} catch (\Exception $e) {
    echo "[ERROR] " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}

echo "\n--- DONE ---\n";
echo "VM ID: {$vm->id}\n";
echo "PVE VMID: {$nextid}\n";
