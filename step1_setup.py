#!/usr/bin/env python3
"""Step 1: Download Debian 12 LXC template + add new node + create product"""

import paramiko, json, requests, time, urllib3
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

def post(url, data, token=None):
    headers = {"Accept": "application/json", "Content-Type": "application/json"}
    if token: headers["Authorization"] = f"Bearer {token}"
    return requests.post(url, json=data, headers=headers, timeout=30, verify=False)

def get(url, token=None):
    headers = {"Accept": "application/json"}
    if token: headers["Authorization"] = f"Bearer {token}"
    return requests.get(url, headers=headers, timeout=30, verify=False)

def pve_api(method, path, data=None):
    """Call PVE API via SSH"""
    s = ssh()
    data_str = ""
    if data:
        import urllib.parse
        data_str = " -d '" + urllib.parse.urlencode(data) + "'"
    
    if method == "GET":
        cmd = f"curl -sk -H 'Authorization: PVEAPIToken={PVE_TOKEN}' 'https://127.0.0.1:8006/api2/json{path}'"
    elif method == "POST":
        cmd = f"curl -sk -X POST -H 'Authorization: PVEAPIToken={PVE_TOKEN}' -H 'Content-Type: application/x-www-form-urlencoded'{data_str} 'https://127.0.0.1:8006/api2/json{path}'"
    elif method == "DELETE":
        cmd = f"curl -sk -X DELETE -H 'Authorization: PVEAPIToken={PVE_TOKEN}' 'https://127.0.0.1:8006/api2/json{path}'"
    
    stdin, stdout, stderr = s.exec_command(cmd, timeout=30)
    out = stdout.read().decode()
    s.close()
    try:
        return json.loads(out)
    except:
        return {"raw": out[:500]}

print("=" * 60)
print("🔧 阶段1: 下载 Debian 12 LXC 模板")
print("=" * 60)

# Check if template exists
s = ssh()
cmd = "pveam available 2>/dev/null | grep debian-12"
stdin, stdout, stderr = s.exec_command(cmd, timeout=30)
available = stdout.read().decode().strip()
print(f"[1a] 可用模板:\n{available or '无(可能需要pveam update)'}")

if not available:
    print("\n[1b] 更新 pveam...")
    stdin, stdout, stderr = s.exec_command("pveam update 2>&1 | tail -5", timeout=60)
    print(stdout.read().decode())
    
    stdin, stdout, stderr = s.exec_command("pveam available 2>/dev/null | grep debian-12", timeout=30)
    available = stdout.read().decode().strip()
    print(f"可用模板:\n{available}")

# Check local templates
print("\n[1c] 本地 LXC 模板...")
stdin, stdout, stderr = s.exec_command("pveam list local 2>/dev/null | grep -i debian", timeout=30)
local = stdout.read().decode().strip()
print(f"本地:\n{local or '无'}")

# Download debian-12 template if needed
if "debian-12" not in local.lower():
    print("\n[1d] 下载 debian-12-standard 模板...")
    # Find the exact template name
    stdin, stdout, stderr = s.exec_command("pveam available 2>/dev/null | grep 'debian-12-standard' | head -1", timeout=30)
    tmpl_line = stdout.read().decode().strip()
    if tmpl_line:
        tmpl_name = tmpl_line.split()[0]
        print(f"  模板: {tmpl_name}")
        stdin, stdout, stderr = s.exec_command(f"pveam download local {tmpl_name} 2>&1", timeout=300)
        print(stdout.read().decode())
        print(stderr.read().decode())
    else:
        print("  ❌ 找不到 debian-12-standard 模板!")
else:
    print("  ✅ 已存在")
s.close()

print("\n" + "=" * 60)
print("🔧 阶段2: 添加新节点 pve.ypvps.com:8006")
print("=" * 60)

# First check if pve.ypvps.com resolves on the server
s = ssh()
stdin, stdout, stderr = s.exec_command("host pve.ypvps.com 2>/dev/null || nslookup pve.ypvps.com 2>/dev/null || echo 'no dns'", timeout=15)
print(f"[2a] DNS 解析:\n{stdout.read().decode()}")

# Test: can we connect to pve.ypvps.com:8006 from the server itself?
stdin, stdout, stderr = s.exec_command("curl -sk -o /dev/null -w '%{http_code}' --connect-timeout 5 https://pve.ypvps.com:8006 2>&1", timeout=15)
code = stdout.read().decode().strip()
print(f"[2b] pve.ypvps.com:8006 连通性: HTTP {code}")

s.close()

# Admin login
print("\n[2c] 管理员登录...")
r = post(f"{API}/api/login", {"email": "admin@pve.ypvps.com", "password": "admin123"})
if r.status_code == 200:
    token = r.json().get("data", {}).get("token") or r.json().get("token")
    print(f"  ✅ 登录成功")
else:
    print(f"  ❌ {r.status_code}")
    exit(1)

# Check current nodes via API
print("\n[2d] 当前节点:")
r = get(f"{API}/api/admin/nodes", token)
nodes = []
if r.status_code == 200:
    data = r.json()
    nodes = data.get("data", {}).get("nodes", data.get("data", []))
    if isinstance(nodes, list):
        for n in nodes:
            print(f"  ID={n.get('id')} | {n.get('name')} | {n.get('host')}:{n.get('port')} | {n.get('status')}")

# Add new node - check if one with host pve.ypvps.com already exists
existing = [n for n in nodes if n.get('host') == 'pve.ypvps.com']
if existing:
    print(f"\n[2e] 节点已存在: ID={existing[0].get('id')}, 跳过创建")
    new_node_id = existing[0].get('id')
else:
    print("\n[2e] 添加新节点...")
    node_data = {
        "name": "pve-ypvps",
        "host": "pve.ypvps.com",
        "port": 8006,
        "auth_type": "api_token",
        "api_token": "root@pam!incudal=534b9129-528c-4ec9-9982-1a066d945f1e",
        "description": "pve.ypvps.com 主节点"
    }
    r = post(f"{API}/api/admin/nodes", node_data, token)
    print(f"  Status: {r.status_code}")
    resp = r.json()
    new_node = resp.get("data", {}).get("node", {})
    new_node_id = new_node.get("id")
    print(f"  Node: {json.dumps(new_node, indent=2)}")
    
    # Test connection
    if new_node_id:
        print(f"\n[2f] 测试节点连接...")
        r = post(f"{API}/api/admin/nodes/{new_node_id}/test", {}, token)
        print(f"  Status: {r.status_code}")
        print(f"  {r.text[:300]}")

print("\n" + "=" * 60)
print(f"✅ 阶段1-2完成, 新节点ID: {new_node_id}")
print("=" * 60)
