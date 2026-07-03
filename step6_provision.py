#!/usr/bin/env python3
"""Step 6: Fix all bugs + manually provision LXC + test full management"""

import paramiko, json, requests, urllib3, time
urllib3.disable_warnings()

SSH_HOST = "pve.ypvps.com"
SSH_USER = "root"
SSH_PASS = "thanks123A#"
API = "https://pve.ypvps.com"
PVE_TOKEN = "root@pam!incudal=534b9129-528c-4ec9-9982-1a066d945f1e"
ORDER_ID = 3
USER_ID = 9
NODE_ID = 2
PRODUCT_ID = 9

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
        return requests.post(url, json=data, headers=headers, timeout=60, verify=False)

# Admin login
r = api("POST", f"{API}/api/login", {"email": "admin@pve.ypvps.com", "password": "admin123"})
admin_token = r.json().get("data", {}).get("token") or r.json().get("token")

# User login
r = api("POST", f"{API}/api/login", {"email": "testuser5851@test.com", "password": "Test123456"})
user_token = r.json().get("data", {}).get("token") or r.json().get("token")

s = ssh()

# ============================================================
print("=" * 60)
print("🔧 FIX 1: 修复 Order::pay() status 字段 bug")
print("=" * 60)

# The bug: line checks $order->status, but should check $order->payment_status
stdin, stdout, stderr = s.exec_command(
    "sed -n '144,147p' /var/www/pve-panel/app/Http/Controllers/Api/OrderController.php",
    timeout=15)
print("当前代码:", stdout.read().decode())

# Fix it
fix_cmd = "sed -i \"s/if (\\\$order->status !== 'pending')/if (\\\$order->payment_status !== 'pending')/\" /var/www/pve-panel/app/Http/Controllers/Api/OrderController.php"
stdin, stdout, stderr = s.exec_command(fix_cmd, timeout=15)
print("修复结果:", stderr.read().decode() or "OK")

# Verify
stdin, stdout, stderr = s.exec_command(
    "sed -n '144,147p' /var/www/pve-panel/app/Http/Controllers/Api/OrderController.php",
    timeout=15)
print("修复后:", stdout.read().decode())

# Clear Laravel cache
stdin, stdout, stderr = s.exec_command(
    "cd /var/www/pve-panel && php artisan optimize:clear 2>&1 | tail -3",
    timeout=15)
print("缓存: " + stdout.read().decode().strip())

# ============================================================
print("\n" + "=" * 60)
print("🔧 FIX 2: 创建 transactions 表")
print("=" * 60)

create_sql = r"""CREATE TABLE IF NOT EXISTS transactions (
  id bigint unsigned NOT NULL AUTO_INCREMENT,
  user_id bigint unsigned NOT NULL,
  type varchar(50) NOT NULL DEFAULT 'recharge',
  amount decimal(10,2) NOT NULL DEFAULT 0.00,
  balance_before decimal(10,2) NOT NULL DEFAULT 0.00,
  balance_after decimal(10,2) NOT NULL DEFAULT 0.00,
  description varchar(255) DEFAULT NULL,
  reference_type varchar(50) DEFAULT NULL,
  reference_id bigint unsigned DEFAULT NULL,
  created_at timestamp NULL DEFAULT NULL,
  updated_at timestamp NULL DEFAULT NULL,
  PRIMARY KEY (id),
  KEY transactions_user_id_index (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"""

stdin, stdout, stderr = s.exec_command(
    'mysql -u root pve_panel -e "' + create_sql + '" 2>&1',
    timeout=15)
result = stdout.read().decode() + stderr.read().decode()
print("transactions:", result or "✅ 已存在或创建成功")

# ============================================================
print("\n" + "=" * 60)
print("🔧 FIX 3: 测试支付 (修复后)")
print("=" * 60)

r = api("POST", f"{API}/api/orders/{ORDER_ID}/pay", {"payment_method": "epay"}, user_token)
print(f"pay epay: {r.status_code}")
data = r.json()
print(f"  {json.dumps(data, indent=2)[:500]}")

if r.status_code == 200 and "pay_url" in json.dumps(data):
    pay_url = data.get("data", {}).get("pay_url", "")
    print(f"\n  💳 支付URL: {pay_url}")
    
# ============================================================
print("\n" + "=" * 60)
print("🔧 FIX 4: 模拟支付回调 (直接标记 paid + 开通 VM)")
print("=" * 60)

# Step A: Mark order as paid
print("\n[A] 标记订单为 paid...")
php_cmd = """php -r '
require "/var/www/pve-panel/vendor/autoload.php";
$app = require_once "/var/www/pve-panel/bootstrap/app.php";
$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
$order = \\App\\Models\\Order::find(""" + str(ORDER_ID) + """);
$order->payment_status = "paid";
$order->paid_at = now();
$order->transaction_id = "SIMULATED-" . uniqid();
$order->save();
echo "Order " . $order->order_no . " -> paid\\n";
'"""
stdin, stdout, stderr = s.exec_command(php_cmd, timeout=15)
print("  " + stdout.read().decode().strip() + stderr.read().decode().strip())

# Step B: Check VirtualMachine model
print("\n[B] 检查 VirtualMachine Model...")
stdin, stdout, stderr = s.exec_command(
    "head -60 /var/www/pve-panel/app/Models/VirtualMachine.php",
    timeout=15)
vm_model = stdout.read().decode()
print(vm_model[:1500])

# Step C: Check if VirtualMachine has node/product relations
stdin, stdout, stderr = s.exec_command(
    "grep -n 'node_id\\|product_id\\|user_id\\|fillable' /var/www/pve-panel/app/Models/VirtualMachine.php | head -20",
    timeout=15)
print("VM fillable:", stdout.read().decode()[:500])

# Step D: Create VirtualMachine record + trigger ProxmoxService::createVm
print("\n[C] 创建 VM 记录...")
php_create = """php -r '
require "/var/www/pve-panel/vendor/autoload.php";
$app = require_once "/var/www/pve-panel/bootstrap/app.php";
$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();

$node = \\App\\Models\\Node::find(""" + str(NODE_ID) + """);
$product = \\App\\Models\\Product::find(""" + str(PRODUCT_ID) + """);
$user = \\App\\Models\\User::find(""" + str(USER_ID) + """);

echo "Node: " . $node->name . " | Product: " . $product->name . " | User: " . $user->email . "\\n";

// Create VM record
$vm = \\App\\Models\\VirtualMachine::create([
    "user_id" => """ + str(USER_ID) + """,
    "node_id" => """ + str(NODE_ID) + """,
    "product_id" => """ + str(PRODUCT_ID) + """,
    "order_id" => """ + str(ORDER_ID) + """,
    "name" => "lxc-test-01",
    "hostname" => "lxc-test-01",
    "type" => "lxc",
    "status" => "creating",
    "cpu" => 1,
    "memory" => 512,
    "disk" => 1,
    "bandwidth" => 100,
    "traffic" => 1000,
    "ostemplate" => "local:vztmpl/debian-12-standard_12.12-1_amd64.tar.zst",
    "password" => password_hash("Test@123456", PASSWORD_BCRYPT),
]);
echo "VM created: ID=" . $vm->id . "\\n";

// Link order to VM
$order = \\App\\Models\\Order::find(""" + str(ORDER_ID) + """);
$order->vm_id = $vm->id;
$order->save();
echo "Order linked to VM\\n";

// Now try to provision via ProxmoxService
try {
    $proxmox = new \\App\\Services\\ProxmoxService();
    $result = $proxmox->createVm($vm);
    echo "Provision result: " . json_encode($result) . "\\n";
} catch (Exception $e) {
    echo "Provision ERROR: " . $e->getMessage() . "\\n";
}
'"""
stdin, stdout, stderr = s.exec_command(php_create, timeout=60)
output = stdout.read().decode() + stderr.read().decode()
print("  " + output[:2000])
s.close()

# ============================================================
print("\n" + "=" * 60)
print("🔍 验证: 检查 PVE 是否有新 LXC")
print("=" * 60)

s = ssh()
pve_cmd = "curl -sk -H 'Authorization: PVEAPIToken=" + PVE_TOKEN + "' https://127.0.0.1:8006/api2/json/nodes/pve/lxc 2>/dev/null"
stdin, stdout, stderr = s.exec_command(pve_cmd, timeout=15)
try:
    lxc_data = json.loads(stdout.read().decode())
    cts = lxc_data.get("data", [])
    print(f"PVE LXC 总数: {len(cts)}")
    for c in cts:
        print(f"  VMID={str(c.get('vmid','?')):5s}  name={str(c.get('name','?')):25s}  status={c.get('status','?')}")
except:
    print("(解析失败)")

# Check VMs via API
print("\n用户 VM 列表 (API):")
r = api("GET", f"{API}/api/vms", user_token)
print(f"  {r.status_code}: {r.text[:500]}")

# Admin VM list
print("\n管理员 VM 列表:")
r = api("GET", f"{API}/api/admin/vms", admin_token)
print(f"  {r.status_code}: {r.text[:500]}")

s.close()

print("\n" + "=" * 60)
print("✅ 修复完成! 检查上面 provision result 和 PVE LXC 列表")
print("=" * 60)
