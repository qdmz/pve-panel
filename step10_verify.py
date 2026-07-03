#!/usr/bin/env python3
"""Step 10: Verify container + fix remaining issues"""

import paramiko, json, requests, urllib3
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

# ============================================================
print("=" * 60)
print("🔍 验证 VMID=100 容器配置")
print("=" * 60)

# Config
cfg_cmd = "curl -sk -H 'Authorization: PVEAPIToken=" + PVE_TOKEN + "' https://127.0.0.1:8006/api2/json/nodes/pve/lxc/100/config 2>/dev/null"
stdin, stdout, stderr = s.exec_command(cfg_cmd, timeout=15)
cfg = json.loads(stdout.read().decode()).get("data", {})
print("容器配置:")
for k, v in sorted(cfg.items()):
    print(f"  {k}: {v}")

# Status
status_cmd = "curl -sk -H 'Authorization: PVEAPIToken=" + PVE_TOKEN + "' https://127.0.0.1:8006/api2/json/nodes/pve/lxc/100/status/current 2>/dev/null"
stdin, stdout, stderr = s.exec_command(status_cmd, timeout=15)
status = json.loads(stdout.read().decode()).get("data", {})
print(f"\n容器状态: {status.get('status','?')}")
print(f"  CPU: {status.get('cpu',0)}")
print(f"  Memory: {status.get('mem',0)/(1024*1024):.0f}MB / {status.get('maxmem',0)/(1024*1024):.0f}MB")
print(f"  Disk: {status.get('disk',0)/(1024*1024*1024):.1f}GB / {status.get('maxdisk',0)/(1024*1024*1024):.1f}GB")
print(f"  Uptime: {status.get('uptime',0)}s")
print(f"  NetIn: {status.get('netin',0)} NetOut: {status.get('netout',0)}")

# Network interfaces
print(f"\n网络接口:")
net_cmd = "curl -sk -H 'Authorization: PVEAPIToken=" + PVE_TOKEN + "' -X GET 'https://127.0.0.1:8006/api2/json/nodes/pve/lxc/100/interfaces' 2>/dev/null"
stdin, stdout, stderr = s.exec_command(net_cmd, timeout=15)
try:
    ifaces = json.loads(stdout.read().decode())
    print(f"  {json.dumps(ifaces, indent=2)[:1000]}")
except:
    print("  (获取失败)")

# Fix createLxc return type
print("\n" + "=" * 60)
print("🔧 修复 ProxmoxService::createLxc() return type")
print("=" * 60)

# Check the return issue
stdin, stdout, stderr = s.exec_command(
    "grep -n 'function createLxc' /var/www/pve-panel/app/Services/ProxmoxService.php",
    timeout=15)
print("createLxc 位置:", stdout.read().decode().strip())

# Look at the function
stdin, stdout, stderr = s.exec_command(
    "grep -A80 'function createLxc' /var/www/pve-panel/app/Services/ProxmoxService.php | head -85",
    timeout=15)
create_lxc = stdout.read().decode()
print(create_lxc[:2000])

# Find the return statement issue
stdin, stdout, stderr = s.exec_command(
    "grep -n 'return ' /var/www/pve-panel/app/Services/ProxmoxService.php | grep -A2 -B2 '46[0-9]' | head -20",
    timeout=15)
print("\nReturn 语句:", stdout.read().decode())

# Check around line 462
stdin, stdout, stderr = s.exec_command(
    "sed -n '455,470p' /var/www/pve-panel/app/Services/ProxmoxService.php",
    timeout=15)
print("\nLine 455-470:", stdout.read().decode())

s.close()

# ============================================================
print("\n" + "=" * 60)
print("🧪 剩余 API 修复验证")
print("=" * 60)

r = api("POST", f"{API}/api/login", {"email": "admin@pve.ypvps.com", "password": "admin123"})
admin_token = r.json().get("data", {}).get("token") or r.json().get("token")
r = api("POST", f"{API}/api/login", {"email": "testuser5851@test.com", "password": "Test123456"})
user_token = r.json().get("data", {}).get("token") or r.json().get("token")

# Profile - try different methods
for method in ["GET", "POST"]:
    r = api(method, f"{API}/api/profile", token=user_token)
    print(f"  {method} /api/profile: {r.status_code}")

# Test epay - try different paths
for path in ["/api/admin/test-epay", "/api/admin/epay/test", "/api/admin/settings/payment/test"]:
    r = api("GET", f"{API}{path}", token=admin_token)
    if r.status_code == 200:
        print(f"  ✅ GET {path}: 200 -> {r.text[:200]}")
    else:
        print(f"  {path}: {r.status_code}")

# VM management endpoints
print("\nVM 管理端点:")
for path in [
    "/api/vms/1",
    "/api/vms/1/start",
    "/api/vms/1/stop",
    "/api/vms/1/restart",
    "/api/vms/1/console",
    "/api/vms/1/reinstall",
    "/api/vms/1/renew",
]:
    for method in ["GET", "POST"]:
        r = api(method, f"{API}{path}", token=user_token)
        if r.status_code not in [404, 405]:
            print(f"  ✅ {method} {path}: {r.status_code}")
            break

print("\n✅ 诊断完成")
