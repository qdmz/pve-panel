#!/usr/bin/env python3
"""Step 9: Upload PHP script, execute, verify"""

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

# Upload PHP script
print("=" * 60)
print("📤 上传并执行 fix_and_provision.php")
print("=" * 60)

with open("fix_and_provision.php", "rb") as f:
    content = f.read()

sftp = s.open_sftp()
with sftp.open("/tmp/fix_and_provision.php", "wb") as f:
    f.write(content)
sftp.close()
print("✅ 已上传")

# Execute
print("\n执行中...")
stdin, stdout, stderr = s.exec_command(
    "cd /var/www/pve-panel && php /tmp/fix_and_provision.php 2>&1",
    timeout=180)
output = stdout.read().decode() + stderr.read().decode()
print(output)

# Clear cache
print("\n清理缓存...")
stdin, stdout, stderr = s.exec_command(
    "cd /var/www/pve-panel && php artisan optimize:clear 2>&1 | tail -2 && systemctl reload php8.2-fpm 2>&1",
    timeout=15)
print(stdout.read().decode().strip())

# ============================================================
print("\n" + "=" * 60)
print("🔍 PVE LXC 验证")
print("=" * 60)

pve_cmd = "curl -sk -H 'Authorization: PVEAPIToken=" + PVE_TOKEN + "' https://127.0.0.1:8006/api2/json/nodes/pve/lxc 2>/dev/null"
stdin, stdout, stderr = s.exec_command(pve_cmd, timeout=15)
cts = json.loads(stdout.read().decode()).get("data", [])

new_ct = [c for c in cts if c.get('name') == 'lxc-test-01']
if new_ct:
    vmid = new_ct[0]['vmid']
    print(f"✅ lxc-test-01 已创建! VMID={vmid} status={new_ct[0].get('status')}")
    
    # Get config
    cfg_cmd = "curl -sk -H 'Authorization: PVEAPIToken=" + PVE_TOKEN + "' https://127.0.0.1:8006/api2/json/nodes/pve/lxc/" + str(vmid) + "/config 2>/dev/null"
    stdin, stdout, stderr = s.exec_command(cfg_cmd, timeout=15)
    cfg = json.loads(stdout.read().decode()).get("data", {})
    print("\n  完整配置:")
    for k, v in sorted(cfg.items()):
        print(f"    {k}: {v}")
else:
    print("❌ lxc-test-01 未找到!")
    print(f"当前 PVE LXC ({len(cts)}):")
    for c in cts:
        print(f"  VMID={c.get('vmid')} name={c.get('name')} status={c.get('status')}")

s.close()

# ============================================================
print("\n" + "=" * 60)
print("🧪 API 管理功能总览")
print("=" * 60)

r = api("POST", f"{API}/api/login", {"email": "admin@pve.ypvps.com", "password": "admin123"})
admin_token = r.json().get("data", {}).get("token") or r.json().get("token")

r = api("POST", f"{API}/api/login", {"email": "testuser5851@test.com", "password": "Test123456"})
user_token = r.json().get("data", {}).get("token") or r.json().get("token")

results = {}
test_suite = [
    ("用户VM列表", "GET", "/api/vms", user_token),
    ("管理员VM", "GET", "/api/admin/vms", admin_token),
    ("管理员节点", "GET", "/api/admin/nodes", admin_token),
    ("管理员产品", "GET", "/api/admin/products", admin_token),
    ("管理员订单", "GET", "/api/admin/orders", admin_token),
    ("管理员仪表板", "GET", "/api/admin/dashboard", admin_token),
    ("我的订单", "GET", "/api/orders", user_token),
    ("产品浏览", "GET", "/api/products", None),
    ("我的资料", "GET", "/api/profile", user_token),
    ("工单系统", "GET", "/api/tickets", user_token),
    ("公告列表", "GET", "/api/announcements", None),
    ("账单记录", "GET", "/api/billing", user_token),
    ("支付测试", "GET", "/api/admin/test-epay", admin_token),
]

for name, method, path, tok in test_suite:
    r = api(method, f"{API}{path}", token=tok)
    status = "✅" if r.status_code == 200 else f"⚠️{r.status_code}"
    results[name] = r.status_code
    detail = ""
    if r.status_code == 200:
        data = r.json().get("data", {})
        if isinstance(data, list):
            detail = f"({len(data)} items)"
        elif isinstance(data, dict):
            for key in ["vms", "nodes", "products", "orders", "tickets"]:
                if key in data and isinstance(data[key], list):
                    detail = f"({len(data[key])} {key})"
                    break
    print(f"  {status} {name:12s} {path}{detail}")

# Print summary
print("\n" + "=" * 60)
print("📊 测试汇总")
print("=" * 60)
passed = sum(1 for c in results.values() if c == 200)
print(f"通过: {passed}/{len(results)}")
for name, code in results.items():
    if code != 200:
        print(f"  ❌ {name}: HTTP {code}")
