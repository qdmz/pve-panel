#!/usr/bin/env python3
"""Final: Upload php file and test"""

import paramiko, requests, urllib3, time
urllib3.disable_warnings()

s = paramiko.SSHClient()
s.set_missing_host_key_policy(paramiko.AutoAddPolicy())
s.connect('pve.ypvps.com', 22, 'root', 'thanks123A#')

# Upload the dedicated PHP file we already wrote
with open('fix_and_provision.php', 'rb') as f:
    # But we need a separate fix file for the POSTFIELDS issue
    pass

# Write the fix PHP directly to server via SFTP
fix_content = b'<?php\n'
fix_content += b'$f = "/var/www/pve-panel/app/Services/ProxmoxService.php";\n'
fix_content += b'$c = file_get_contents($f);\n'
fix_content += b'$old = \'if (!empty($data) && in_array(strtoupper($method), \';\n'
fix_content += b'$new = \'if (in_array(strtoupper($method), \';\n'
fix_content += b'$c = str_replace($old, $new, $c, $cnt);\n'
fix_content += b'file_put_contents($f, $c);\n'
fix_content += b'echo "FIXED: $cnt\\n";\n'

sftp = s.open_sftp()
with sftp.open('/tmp/fix_post.php', 'wb') as f:
    f.write(fix_content)
sftp.close()

stdin, stdout, stderr = s.exec_command('php /tmp/fix_post.php 2>&1', timeout=15)
print('Fix:', stdout.read().decode().strip())

# Verify
stdin, stdout, stderr = s.exec_command("grep -n '!empty.*data.*in_array' /var/www/pve-panel/app/Services/ProxmoxService.php", timeout=10)
result = stdout.read().decode().strip()
print('Check:', result or 'Fixed (no more !empty check)')

# Also verify POSTFIELDS is now set unconditionally
stdin, stdout, stderr = s.exec_command("grep -A2 'in_array.*POST.*PUT.*PATCH' /var/www/pve-panel/app/Services/ProxmoxService.php | head -5", timeout=10)
print('POSTFIELDS:', stdout.read().decode().strip())

# Restart PHP-FPM
stdin, stdout, stderr = s.exec_command('systemctl restart php8.2-fpm && echo RESTARTED', timeout=15)
print('Restart:', stdout.read().decode().strip())

# Now test start/stop via Laravel directly
test_content = b'''<?php
require "/var/www/pve-panel/vendor/autoload.php";
$app = require_once "/var/www/pve-panel/bootstrap/app.php";
$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();

$vm = \\App\\Models\\VirtualMachine::find(1);
echo "VM #1: vmid={$vm->vm_id} status={$vm->status}\\n";

$p = new \\App\\Services\\ProxmoxService();
try {
    $r = $p->stopVm($vm);
    echo "STOP OK: " . json_encode($r) . "\\n";
} catch(Exception $e) {
    echo "STOP FAIL: {$e->getMessage()}\\n";
}

sleep(2);

try {
    $r = $p->startVm($vm);
    echo "START OK: " . json_encode($r) . "\\n";
} catch(Exception $e) {
    echo "START FAIL: {$e->getMessage()}\\n";
}

// Verify status
$ch = curl_init("https://127.0.0.1:8006/api2/json/nodes/pve/lxc/100/status/current");
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true,CURLOPT_SSL_VERIFYPEER=>false,CURLOPT_SSL_VERIFYHOST=>false,CURLOPT_HTTPHEADER=>["Authorization: PVEAPIToken=root@pam!incudal=534b9129-528c-4ec9-9982-1a066d945f1e"]]);
$d = json_decode(curl_exec($ch),true)["data"]; curl_close($ch);
echo "VERIFY: status={$d["status"]} uptime={$d["uptime"]}s\\n";
'''

with sftp.open('/tmp/test_control2.php', 'wb') as f:
    f.write(test_content)
sftp.close()

print('\n=== Direct test ===')
stdin, stdout, stderr = s.exec_command('cd /var/www/pve-panel && php /tmp/test_control2.php 2>&1', timeout=60)
print(stdout.read().decode())

# API test
print('\n=== API test ===')
r = requests.post('https://pve.ypvps.com/api/login',
    json={'email':'testuser5851@test.com','password':'Test123456'},
    headers={'Accept':'application/json'}, verify=False, timeout=10)
token = r.json().get('data',{}).get('token') or r.json().get('token')

for action in ['stop','start','restart']:
    r = requests.post(f'https://pve.ypvps.com/api/vms/1/{action}',
        headers={'Authorization':f'Bearer {token}','Accept':'application/json'}, verify=False, timeout=15)
    print(f'  {action}: {r.status_code} -> {r.text[:120]}')
    time.sleep(3)

s.close()
print('\nDONE')
