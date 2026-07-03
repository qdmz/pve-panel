#!/usr/bin/env python3
"""Step 7: Final fix + provision LXC"""

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
        return requests.post(url, json=data, headers=headers, timeout=30, verify=False)

s = ssh()

# ============================================================
print("=" * 60)
print("🔧 FIX 1: 正确修复 Order::pay() → payment_status")
print("=" * 60)

# Check exact line
stdin, stdout, stderr = s.exec_command(
    "grep -n 'order->status' /var/www/pve-panel/app/Http/Controllers/Api/OrderController.php",
    timeout=15)
print("当前:", stdout.read().decode().strip())

# Check if sed worked (it was $order->status not \$order->status)
# Try direct replacement with perl
fix = "cd /var/www/pve-panel && php -r \""
fix += "$c=file_get_contents('app/Http/Controllers/Api/OrderController.php');"
fix += "$c=str_replace(\\\"\\$order->status !== 'pending'\\\",\\\"\\$order->payment_status !== 'pending'\\\",$c);"
fix += "file_put_contents('app/Http/Controllers/Api/OrderController.php',$c);"
fix += "echo 'FIXED';\""
stdin, stdout, stderr = s.exec_command(fix, timeout=15)
print("修复:", stdout.read().decode().strip() + stderr.read().decode().strip())

# Verify
stdin, stdout, stderr = s.exec_command(
    "grep -n 'payment_status.*pending' /var/www/pve-panel/app/Http/Controllers/Api/OrderController.php",
    timeout=15)
print("验证:", stdout.read().decode().strip())

# Clear cache & restart
stdin, stdout, stderr = s.exec_command(
    "cd /var/www/pve-panel && php artisan optimize:clear 2>&1 | tail -2 && systemctl reload php8.2-fpm",
    timeout=15)
print("重启: " + stdout.read().decode().strip())

# ============================================================
print("\n" + "=" * 60)
print("🔧 FIX 2: 处理 vm_id NOT NULL + 创建 VM + provision")
print("=" * 60)

# Check vm_id in migration
print("\n[a] virtual_machines 表结构...")
stdin, stdout, stderr = s.exec_command(
    "mysql -u root pve_panel -e 'SHOW CREATE TABLE virtual_machines\\G' 2>/dev/null | head -80",
    timeout=15)
print(stdout.read().decode()[:1500])

# Check if vm_id has default or auto-increment
stdin, stdout, stderr = s.exec_command(
    "mysql -u root pve_panel -e 'DESCRIBE virtual_machines' 2>/dev/null",
    timeout=15)
print(stdout.read().decode()[:1000])

# Find next available VMID in PVE
print("\n[b] 获取下一个可用 VMID...")
stdin, stdout, stderr = s.exec_command(
    "curl -sk -H 'Authorization: PVEAPIToken=" + PVE_TOKEN + "' https://127.0.0.1:8006/api2/json/cluster/nextid 2>/dev/null",
    timeout=15)
nextid = json.loads(stdout.read().decode()).get("data", "?")
print(f"  PVE nextid: {nextid}")

# Now create VM with vm_id
print("\n[c] 创建 VM 记录 + provision...")
php_create = "php -r '"
php_create += 'require "/var/www/pve-panel/vendor/autoload.php";'
php_create += '$app = require_once "/var/www/pve-panel/bootstrap/app.php";'
php_create += '$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();'
php_create += '$vm = \\App\\Models\\VirtualMachine::create(['
php_create += '"user_id"=>' + str(USER_ID) + ','
php_create += '"node_id"=>' + str(NODE_ID) + ','
php_create += '"product_id"=>' + str(PRODUCT_ID) + ','
php_create += '"order_id"=>' + str(ORDER_ID) + ','
php_create += '"vm_id"=>"' + str(nextid) + '",'
php_create += '"name"=>"lxc-test-01",'
php_create += '"hostname"=>"lxc-test-01",'
php_create += '"type"=>"lxc",'
php_create += '"status"=>"creating",'
php_create += '"cpu"=>1,'
php_create += '"memory"=>512,'
php_create += '"disk"=>1,'
php_create += '"bandwidth"=>100,'
php_create += '"traffic_limit"=>1000,'
php_create += '"traffic_used"=>0,'
php_create += '"os_template"=>"local:vztmpl/debian-12-standard_12.12-1_amd64.tar.zst",'
php_create += '"root_password"=>"Test@123456",'
php_create += '"ip"=>"auto",'
php_create += ']);'
php_create += 'echo "[OK] VM record ID=".$vm->id." vm_id=".$vm->vm_id."\\n";'
php_create += '$proxmox = new \\App\\Services\\ProxmoxService();'
php_create += 'try { $r = $proxmox->createVm($vm); echo "[PROVISION] ".json_encode($r)."\\n"; }'
php_create += 'catch(Exception $e) { echo "[ERROR] ".$e->getMessage()."\\n"; echo $e->getTraceAsString()."\\n"; }'
php_create += "'"

stdin, stdout, stderr = s.exec_command(php_create, timeout=120)
output = stdout.read().decode() + "\nSTDERR:\n" + stderr.read().decode()
print("  " + output[:3000])

# ============================================================
print("\n" + "=" * 60)
print("🔍 验证: PVE LXC 状态")
print("=" * 60)

pve_cmd = "curl -sk -H 'Authorization: PVEAPIToken=" + PVE_TOKEN + "' https://127.0.0.1:8006/api2/json/nodes/pve/lxc 2>/dev/null"
stdin, stdout, stderr = s.exec_command(pve_cmd, timeout=15)
cts = json.loads(stdout.read().decode()).get("data", [])
print(f"PVE LXC 总数: {len(cts)}")
for c in cts:
    print(f"  VMID={str(c.get('vmid','?')):5s}  name={str(c.get('name','?')):25s}  status={c.get('status','?')}")

# Check new VM networking
new_ct = [c for c in cts if str(c.get('vmid')) == str(nextid)]
if new_ct:
    ct = new_ct[0]
    print(f"\n🖥️  新容器详情:")
    # Get config
    cfg_cmd = "curl -sk -H 'Authorization: PVEAPIToken=" + PVE_TOKEN + "' https://127.0.0.1:8006/api2/json/nodes/pve/lxc/" + str(nextid) + "/config 2>/dev/null"
    stdin, stdout, stderr = s.exec_command(cfg_cmd, timeout=15)
    try:
        cfg = json.loads(stdout.read().decode())
        cfg_data = cfg.get("data", {})
        for k, v in cfg_data.items():
            if 'net' in k or 'ip' in k.lower() or 'bridge' in k or 'host' in k or 'ostemplate' in k or 'root' in k:
                print(f"  {k}: {v}")
        print(f"  完整配置键: {list(cfg_data.keys())}")
    except:
        print("  (配置解析失败)")

    # Network interfaces
    print("\n  网络接口:")
    net_cmd = "curl -sk -H 'Authorization: PVEAPIToken=" + PVE_TOKEN + "' https://127.0.0.1:8006/api2/json/nodes/pve/lxc/" + str(nextid) + "/interfaces 2>/dev/null"
    stdin, stdout, stderr = s.exec_command(net_cmd, timeout=15)
    print("  " + stdout.read().decode()[:500])

s.close()

# ============================================================
# Re-login due to expired tokens
print("\n\n" + "=" * 60)
print("🔑 重新登录获取 Token")
print("=" * 60)

r = api("POST", f"{API}/api/login", {"email": "admin@pve.ypvps.com", "password": "admin123"})
admin_token = r.json().get("data", {}).get("token") or r.json().get("token")
print(f"Admin token: {'OK' if admin_token else 'FAIL'}")

r = api("POST", f"{API}/api/login", {"email": "testuser5851@test.com", "password": "Test123456"})
user_token = r.json().get("data", {}).get("token") or r.json().get("token")
print(f"User token: {'OK' if user_token else 'FAIL'}")

# ============================================================
print("\n" + "=" * 60)
print("🧪 管理功能测试")
print("=" * 60)

# VMs
r = api("GET", f"{API}/api/vms", token=user_token)
print(f"[1] /api/vms: {r.status_code}")
if r.status_code == 200:
    vms = r.json().get("data", {}).get("vms", r.json().get("data", []))
    if isinstance(vms, list):
        for vm in vms[:3]:
            print(f"    VM: {vm.get('name','?')} | {vm.get('type','?')} | status={vm.get('status','?')} | IP={vm.get('ip','?')} | IPv6={vm.get('ipv6_address','?')}")

# Admin VMs
r = api("GET", f"{API}/api/admin/vms", token=admin_token)
print(f"[2] /api/admin/vms: {r.status_code}")
if r.status_code == 200:
    admin_vms = r.json().get("data", {}).get("vms", r.json().get("data", []))
    if isinstance(admin_vms, list):
        print(f"    总数: {len(admin_vms)}")
        for vm in admin_vms[:3]:
            print(f"    {json.dumps(vm, indent=2)[:300]}")

# Orders
r = api("GET", f"{API}/api/orders", token=user_token)
print(f"[3] /api/orders: {r.status_code}")
if r.status_code == 200:
    orders = r.json().get("data", {}).get("orders", r.json().get("data", []))
    if isinstance(orders, list):
        for o in orders[:3]:
            print(f"    #{o.get('order_no','?')} | {o.get('payment_status','?')} | ¥{o.get('amount','?')}")

# Tickets
r = api("GET", f"{API}/api/tickets", token=user_token)
print(f"[4] /api/tickets: {r.status_code}")

# Profile
r = api("GET", f"{API}/api/profile", token=user_token)
print(f"[5] /api/profile: {r.status_code}")

# Billing
r = api("GET", f"{API}/api/billing", token=user_token)
print(f"[6] /api/billing: {r.status_code}")

# Notifications
r = api("GET", f"{API}/api/notifications", token=user_token)
print(f"[7] /api/notifications: {r.status_code}")

print("\n" + "=" * 60)
print("✅ 全流程测试完成!")
print("=" * 60)
