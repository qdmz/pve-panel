<?php
require "/var/www/pve-panel/vendor/autoload.php";
$app = require_once "/var/www/pve-panel/bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$vm = \App\Models\VirtualMachine::find(1);
echo "VM #1: status={$vm->status} vmid={$vm->vm_id}\n";

// Check PVE status first
$ch = curl_init("https://127.0.0.1:8006/api2/json/nodes/pve/lxc/100/status/current");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER=>true, CURLOPT_SSL_VERIFYPEER=>false, CURLOPT_SSL_VERIFYHOST=>false,
    CURLOPT_HTTPHEADER=>["Authorization: PVEAPIToken=root@pam!incudal=534b9129-528c-4ec9-9982-1a066d945f1e"]
]);
$pve_status = json_decode(curl_exec($ch), true)["data"]["status"] ?? "unknown";
curl_close($ch);
echo "PVE status: $pve_status\n";

// Sync DB
if ($vm->status != $pve_status) {
    $vm->status = $pve_status;
    $vm->save();
    echo "DB synced: {$vm->status}\n";
}

// Now test start via ProxmoxService
echo "\n=== Testing startVm via ProxmoxService ===\n";
try {
    $proxmox = new \App\Services\ProxmoxService();
    
    // First stop if running
    if ($pve_status === "running") {
        echo "Stopping first...\n";
        $proxmox->stopVm($vm);
        sleep(3);
    }
    
    echo "Starting...\n";
    $result = $proxmox->startVm($vm);
    echo "OK: " . json_encode($result) . "\n";
    
    $vm->status = "running";
    $vm->save();
    echo "Status updated to running\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}

echo "\n=== FINAL STATUS ===\n";
$ch = curl_init("https://127.0.0.1:8006/api2/json/nodes/pve/lxc/100/status/current");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER=>true, CURLOPT_SSL_VERIFYPEER=>false, CURLOPT_SSL_VERIFYHOST=>false,
    CURLOPT_HTTPHEADER=>["Authorization: PVEAPIToken=root@pam!incudal=534b9129-528c-4ec9-9982-1a066d945f1e"]
]);
$final = json_decode(curl_exec($ch), true)["data"] ?? [];
curl_close($ch);
echo "PVE: status={$final["status"]} cpu={$final["cpu"]} ram=" . round($final["mem"]/(1024*1024)) . "MB\n";
