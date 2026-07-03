#!/usr/bin/env python3
"""Step 8: Direct PHP script approach - fix all + provision"""

import paramiko, json, requests, urllib3
urllib3.disable_warnings()

SSH_HOST = "pve.ypvps.com"
SSH_USER = "root"
SSH_PASS = "thanks123A#"
API = "https://pve.ypvps.com"
PVE_TOKEN = "root@pam!incudal=534b9129-528c-4ec9-9982-1a066d945f1e"

def ssh():
    c = paramiko.SSHClient()
    c.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    c.connect(SSH_HOST, 22, SSH_USER, SSH_PASS)
    return c

def api(method, url, data=None, token=None):
    headers = {"Accept": "application/json", "Content-Type": "application/json"}
    if token: headers["Authorization"] = f"Bearer {token}"
    if method == "GET":
        return requests.get(url, headers=headers, timeout=30, verify=False)
    elif method == "POST":
        return requests.post(url, json=data, headers=headers, timeout=30, verify=False)

s = ssh()

# ============================================================
print("=" * 60)
print("🔧 写入修复脚本到服务器")
print("=" * 60)

php_script = """<?php
/**
 * Fix Order::pay() + Create VM + Provision LXC
 */
require "/var/www/pve-panel/vendor/autoload.php";
$app = require_once "/var/www/pve-panel/bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "========================================\\n";
echo "FIX 1: OrderController status -> payment_status\\n";
echo "========================================\\n";

// Fix the OrderController
$path = "/var/www/pve-panel/app/Http/Controllers/Api/OrderController.php";
$code = file_get_contents($path);
$original = $code;
$code = str_replace(
    "if (\$order->status !== 'pending')",
    "if (\$order->payment_status !== 'pending')",
    $code,
    $count
);
if ($count > 0) {
    file_put_contents($path, $code);
    echo "[OK] Fixed $count occurrence(s) in OrderController\\n";
} else {
    echo "[INFO] Already fixed or pattern not found\\n";
}

echo "\\n========================================\\n";
echo "FIX 2: Create VM + Provision\\n";
echo "========================================\\n";

$order = \App\Models\Order::find(3);
echo "Order: #{$order->order_no} | payment_status={$order->payment_status} | product_id={$order->product_id}\\n";

$node = \App\Models\Node::find(2);
$product = \App\Models\Product::find(9);
$user = \App\Models\User::find(9);

echo "Node: {$node->name} ({$node->host}:{$node->port})\\n";
echo "Product: {$product->name} ({$product->type})\\n";
echo "User: {$user->email}\\n";

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
echo "PVE nextid: $nextid\\n";

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
echo "VM record: ID={$vm->id} vm_id={$vm->vm_id}\\n";

// Link order to VM
$order->vm_id = $vm->id;
$order->save();

// Try provision
echo "\\n--- PROVISIONING ---\\n";
try {
    $proxmox = new \App\Services\ProxmoxService();
    $result = $proxmox->createVm($vm);
    echo "[OK] Provision: " . json_encode($result) . "\\n";
    
    // Update VM status
    $vm->status = 'running';
    $vm->save();
} catch (\Exception $e) {
    echo "[ERROR] " . $e->getMessage() . "\\n";
    echo $e->getTraceAsString() . "\\n";
}

echo "\\n--- DONE ---\\n";
echo "VM ID: {$vm->id}\\n";
echo "PVE VMID: {$nextid}\\n";
"""

# Write to server
sftp = s.open_sftp()
with sftp.open("/tmp/fix_and_provision.php", "w") as f:
    f.write(php_script)
sftp.close()

# Execute
print("执行修复脚本...")
stdin, stdout, stderr = s.exec_command(
    "cd /var/www/pve-panel && php /tmp/fix_and_provision.php 2>&1",
    timeout=120)
output = stdout.read().decode() + stderr.read().decode()
print(output)

# Clear cache
stdin, stdout, stderr = s.exec_command(
    "cd /var/www/pve-panel && php artisan optimize:clear 2>&1 | tail -2 && systemctl reload php8.2-fpm",
    timeout=15)
print("缓存: " + stdout.read().decode().strip())

# ============================================================
print("\n" + "=" * 60)
print("🔍 验证 PVE 新建容器")
print("=" * 60)

pve_cmd = "curl -sk -H 'Authorization: PVEAPIToken=" + PVE_TOKEN + "' https://127.0.0.1:8006/api2/json/nodes/pve/lxc 2>/dev/null"
stdin, stdout, stderr = s.exec_command(pve_cmd, timeout=15)
cts = json.loads(stdout.read().decode()).get("data", [])
print(f"PVE LXC 总数: {len(cts)}")
for c in cts:
    print(f"  VMID={str(c.get('vmid','?')):5s}  name={str(c.get('name','?')):25s}  status={c.get('status','?')}")

# Get new container config
new_ct = [c for c in cts if c.get('name') == 'lxc-test-01']
if new_ct:
    vmid = new_ct[0]['vmid']
    print(f"\n🖥️  lxc-test-01 (VMID={vmid}) 配置:")
    
    cfg_cmd = "curl -sk -H 'Authorization: PVEAPIToken=" + PVE_TOKEN + "' https://127.0.0.1:8006/api2/json/nodes/pve/lxc/" + str(vmid) + "/config 2>/dev/null"
    stdin, stdout, stderr = s.exec_command(cfg_cmd, timeout=15)
    cfg = json.loads(stdout.read().decode()).get("data", {})
    for k, v in sorted(cfg.items()):
        print(f"    {k}: {v}")
    
    # Check network config
    print("\n  网络:")
    for k, v in cfg.items():
        if 'net' in k:
            print(f"    {k}: {v}")

s.close()

# ============================================================
print("\n" + "=" * 60)
print("🧪 API 管理功能测试")
print("=" * 60)

r = api("POST", f"{API}/api/login", {"email": "admin@pve.ypvps.com", "password": "admin123"})
admin_token = r.json().get("data", {}).get("token") or r.json().get("token")

r = api("POST", f"{API}/api/login", {"email": "testuser5851@test.com", "password": "Test123456"})
user_token = r.json().get("data", {}).get("token") or r.json().get("token")

tests = [
    ("用户VM列表", "GET", "/api/vms", user_token),
    ("管理员VM列表", "GET", "/api/admin/vms", admin_token),
    ("我的订单", "GET", "/api/orders", user_token),
    ("产品列表", "GET", "/api/products", None),
    ("我的资料", "GET", "/api/profile", user_token),
    ("工单列表", "GET", "/api/tickets", user_token),
    ("公告列表", "GET", "/api/announcements", None),
    ("账单记录", "GET", "/api/billing", user_token),
]

for name, method, path, tok in tests:
    r = api(method, f"{API}{path}", token=tok)
    status = "✅" if r.status_code == 200 else f"⚠️{r.status_code}"
    detail = ""
    if r.status_code == 200 and "vms" in path:
        data = r.json().get("data", {})
        if isinstance(data, list):
            detail = f" ({len(data)} items)"
        elif isinstance(data, dict):
            vms = data.get("vms", data.get("data", []))
            detail = f" ({len(vms) if isinstance(vms, list) else '?'} items)"
    print(f"  {status} {name:12s} {method} {path}{detail}")

print("\n" + "=" * 60)
print("✅ 完成!")
print("=" * 60)
