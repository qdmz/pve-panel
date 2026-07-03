<?php
/**
 * Fix: startVm/stopVm/restartVm method signature + add HTTP/1.0 to pvesh
 */
require "/var/www/pve-panel/vendor/autoload.php";

echo "=== FIX: ProxmoxService HTTP/1.0 + method signatures ===\n";

$f = "/var/www/pve-panel/app/Services/ProxmoxService.php";
$code = file_get_contents($f);

// Fix 1: Add CURLOPT_HTTP_VERSION after CURLOPT_TIMEOUT
$code = str_replace(
    'CURLOPT_TIMEOUT        => 30,',
    'CURLOPT_TIMEOUT        => 30,' . "\n" .
    '            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_0,',
    $code
);
echo "[1] HTTP/1.0 fix: done\n";

// Fix 2: Check startVm implementation
if (strpos($code, 'function startVm(VirtualMachine') !== false) {
    // Method already expects VirtualMachine — controller needs fixing instead
    echo "[2] startVm expects VirtualMachine — fixing controller\n";
    
    $ctrl = "/var/www/pve-panel/app/Http/Controllers/Api/VmController.php";
    $ctrl_code = file_get_contents($ctrl);
    
    // Fix: $proxmox->startVm($vm->node->name, $vm->vmid) → $proxmox->startVm($vm)
    $ctrl_code = str_replace(
        '$proxmox->startVm($vm->node->name, $vm->vmid)',
        '$proxmox->startVm($vm)',
        $ctrl_code,
        $cnt1
    );
    $ctrl_code = str_replace(
        '$proxmox->stopVm($vm->node->name, $vm->vmid)',
        '$proxmox->stopVm($vm)',
        $ctrl_code,
        $cnt2
    );
    $ctrl_code = str_replace(
        '$proxmox->restartVm($vm->node->name, $vm->vmid)',
        '$proxmox->restartVm($vm)',
        $ctrl_code,
        $cnt3
    );
    
    file_put_contents($ctrl, $ctrl_code);
    echo "  startVm: $cnt1, stopVm: $cnt2, restartVm: $cnt3 replacements\n";
}

file_put_contents($f, $code);

// Also fix Admin VmController
$admin_ctrl = "/var/www/pve-panel/app/Http/Controllers/Admin/VmController.php";
if (file_exists($admin_ctrl)) {
    $admin_code = file_get_contents($admin_ctrl);
    $admin_code = str_replace(
        '$proxmox->startVm($vm->node->name, $vm->vmid)',
        '$proxmox->startVm($vm)',
        $admin_code
    );
    $admin_code = str_replace(
        '$proxmox->stopVm($vm->node->name, $vm->vmid)',
        '$proxmox->stopVm($vm)',
        $admin_code
    );
    $admin_code = str_replace(
        '$proxmox->restartVm($vm->node->name, $vm->vmid)',
        '$proxmox->restartVm($vm)',
        $admin_code
    );
    file_put_contents($admin_ctrl, $admin_code);
    echo "[3] Admin VmController: fixed\n";
}

// Clear cache
echo "[4] Cache clear...\n";
shell_exec("cd /var/www/pve-panel && php artisan optimize:clear 2>&1");
shell_exec("systemctl reload php8.2-fpm 2>&1");

echo "\n=== DONE ===\n";
echo "Check: php-fpm restarted, cache cleared\n";
