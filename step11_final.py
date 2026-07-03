#!/usr/bin/env python3
"""Step 11: Start container + fix createLxc + final verification"""

import paramiko, json, requests, urllib3, time
urllib3.disable_warnings()

SSH_HOST = "pve.ypvps.com"
SSH_USER = "root"
SSH_PASS = "thanks123A#"
API = "https://pve.ypvps.com"
PVE_TOKEN = "root@pam!incudal=534b9129-528c-4ec9-9982-1a066d945f1e"

def api(method, url, data=None, token=None):
    headers = {"Accept": "application/json", "Content-Type": "application/json"}
    if token: headers["Authorization"] = f"Bearer {token}"
    if method == "GET":
        return requests.get(url, headers=headers, timeout=30, verify=False)
    else:
        return requests.post(url, json=data, headers=headers, timeout=30, verify=False)

s = paramiko.SSHClient()
s.set_missing_host_key_policy(paramiko.AutoAddPolicy())
s.connect(SSH_HOST, 22, SSH_USER, SSH_PASS)

# Upload and execute
with open("start_container.php", "rb") as f:
    content = f.read()

sftp = s.open_sftp()
with sftp.open("/tmp/start_container.php", "wb") as f:
    f.write(content)
sftp.close()

print("=" * 60)
print("🚀 启动容器 + 完整验证")
print("=" * 60)

stdin, stdout, stderr = s.exec_command("cd /var/www/pve-panel && php /tmp/start_container.php 2>&1", timeout=60)
print(stdout.read().decode())

# Wait & check
print("\n等待容器启动...")
time.sleep(3)

# Verify running
stdin, stdout, stderr = s.exec_command(
    "curl -sk -H 'Authorization: PVEAPIToken=" + PVE_TOKEN + "' https://127.0.0.1:8006/api2/json/nodes/pve/lxc/100/status/current 2>/dev/null | python3 -c \"import sys,json; d=json.load(sys.stdin)['data']; print(f'Status: {d[\\\"status\\\"]} CPU: {d[\\\"cpu\\\"]} RAM: {d[\\\"mem\\\"]/(1024*1024):.0f}MB Uptime: {d[\\\"uptime\\\"]}s')\"",
    timeout=15)
print("容器: " + stdout.read().decode().strip())

s.close()

# ============================================================
print("\n" + "=" * 60)
print("🧪 VM 管理功能测试")
print("=" * 60)

r = api("POST", f"{API}/api/login", {"email": "admin@pve.ypvps.com", "password": "admin123"})
admin_token = r.json().get("data", {}).get("token") or r.json().get("token")
r = api("POST", f"{API}/api/login", {"email": "testuser5851@test.com", "password": "Test123456"})
user_token = r.json().get("data", {}).get("token") or r.json().get("token")

# VM details
r = api("GET", f"{API}/api/vms/1", token=user_token)
print(f"[详情] /api/vms/1: {r.status_code}")
if r.status_code == 200:
    data = r.json().get("data", {})
    if isinstance(data, dict):
        vm = data.get("vm", data)
        print(f"  Name: {vm.get('name')} | Status: {vm.get('status')} | IP: {vm.get('ip')} | IPv6: {vm.get('ipv6_address','N/A')}")

# VM management actions
for action, method in [("start","POST"),("stop","POST"),("restart","POST")]:
    r = api(method, f"{API}/api/vms/1/{action}", token=user_token)
    status = "✅" if r.status_code == 200 else f"HTTP{r.status_code}"
    print(f"  {status} {method} /api/vms/1/{action}: {r.text[:150]}")

# VNC console
r = api("GET", f"{API}/api/vms/1/console", token=user_token)
print(f"  /api/vms/1/console: {r.status_code}")

# Admin VM management
for action in ["start","stop","restart","suspend","unsuspend"]:
    r = api("POST", f"{API}/api/admin/vms/1/{action}", token=admin_token)
    status = f"HTTP{r.status_code}"
    if r.status_code == 200: status = "✅"
    print(f"  Admin {action}: {status} {r.text[:100]}")

# ============================================================
print("\n" + "=" * 60)
print("📊 最终汇总")
print("=" * 60)

all_tests = [
    ("注册", "POST", "/api/register", None),
    ("登录", "POST", "/api/login", None),
    ("产品", "GET", "/api/products", None),
    ("公告", "GET", "/api/announcements", None),
    ("下单", "POST", "/api/orders", user_token),
    ("支付", "POST", "/api/orders/3/pay", user_token),
    ("VM列表", "GET", "/api/vms", user_token),
    ("VM详情", "GET", "/api/vms/1", user_token),
    ("VM启动", "POST", "/api/vms/1/start", user_token),
    ("VM停止", "POST", "/api/vms/1/stop", user_token),
    ("重启", "POST", "/api/vms/1/restart", user_token),
    ("控制台", "GET", "/api/vms/1/console", user_token),
    ("工单", "GET", "/api/tickets", user_token),
    ("账单", "GET", "/api/billing", user_token),
    ("管理员节点", "GET", "/api/admin/nodes", admin_token),
    ("管理员VM", "GET", "/api/admin/vms", admin_token),
    ("管理员产品", "GET", "/api/admin/products", admin_token),
    ("管理员订单", "GET", "/api/admin/orders", admin_token),
    ("仪表板", "GET", "/api/admin/dashboard", admin_token),
    ("节点列表", "GET", "/api/admin/nodes", admin_token),
]

passed = 0
failed = 0
for name, method, path, tok in all_tests:
    data = None
    if method == "POST" and path == "/api/login":
        data = {"email": "testuser5851@test.com", "password": "Test123456"}
    elif method == "POST" and path == "/api/register":
        data = {"name":"Test","email":"xx@x.com","password":"Aa123456","password_confirmation":"Aa123456"}
    elif method == "POST" and path == "/api/orders":
        data = {"product_id":9,"billing_cycle":"monthly"}
    elif method == "POST" and "pay" in path:
        data = {"payment_method":"epay"}
    r = api(method, f"{API}{path}", data, tok)
    ok = r.status_code in [200, 201]
    if ok: passed += 1
    else: failed += 1
    status = "✅" if ok else f"❌{r.status_code}"
    print(f"  {status} {name}")

print(f"\n通过: {passed}/{len(all_tests)} | 失败: {failed}")
