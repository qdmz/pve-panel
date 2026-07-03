#!/usr/bin/env python3
"""Step 2: Configure node NAT/IPv6 → create LXC product → full E2E test"""

import paramiko, json, requests, time, urllib3, random, string
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
    elif method == "PUT":
        return requests.put(url, json=data, headers=headers, timeout=30, verify=False)
    elif method == "DELETE":
        return requests.delete(url, headers=headers, timeout=30, verify=False)

def pve(ssh, cmd):
    stdin, stdout, stderr = ssh.exec_command(cmd, timeout=30)
    out = stdout.read().decode()
    try:
        return json.loads(out)
    except:
        return out

# Admin login
print("=" * 60)
print("🔑 管理员登录")
r = api("POST", f"{API}/api/login", {"email": "admin@pve.ypvps.com", "password": "admin123"})
token = r.json().get("data", {}).get("token") or r.json().get("token")
print(f"✅ token: {token[:30]}...")

# ============================================================
print("\n" + "=" * 60)
print("🔧 阶段2: 配置节点 NAT + IPv6")
print("=" * 60)

# First check node admin update endpoint
node_id = 2
print("\n[2a] 查看节点更新 API...")
r = api("GET", f"{API}/api/admin/nodes/{node_id}", token=token)
print(f"  GET node/{node_id}: {r.status_code}")

# Try update node with NAT config
print("\n[2b] 更新节点 NAT + IPv6 配置...")
update_data = {
    "nat_enabled": True,
    "nat_start_port": 20000,
    "nat_end_port": 30000,
    "nat_network": "10.10.10.0/24",
    "bridge": "vmbr0",
    "ipv6_bridge": "vmbr2",
    "storage": "local",
}
r = api("PUT", f"{API}/api/admin/nodes/{node_id}", update_data, token=token)
print(f"  PUT node/{node_id}: {r.status_code}")
if r.status_code != 200:
    print(f"  Body: {r.text[:500]}")

# Also try POST for update
if r.status_code >= 400:
    print("  尝试 PATCH...")
    r = requests.patch(f"{API}/api/admin/nodes/{node_id}", json=update_data, 
                       headers={"Accept": "application/json", "Content-Type": "application/json", "Authorization": f"Bearer {token}"},
                       timeout=30, verify=False)
    print(f"  PATCH: {r.status_code}")
    print(f"  {r.text[:300]}")

# Verify node after update
print("\n[2c] 验证节点配置...")
r = api("GET", f"{API}/api/admin/nodes/{node_id}", token=token)
node_info = r.json().get("data", {}).get("node", r.json().get("data", {}))
print(f"  nat_enabled: {node_info.get('nat_enabled')}")
print(f"  nat_ports: {node_info.get('nat_start_port')}-{node_info.get('nat_end_port')}")
print(f"  nat_network: {node_info.get('nat_network')}")
print(f"  bridge: {node_info.get('bridge')}")
print(f"  ipv6_bridge: {node_info.get('ipv6_bridge')}")
print(f"  storage: {node_info.get('storage')}")

# Sync templates
print("\n[2d] 同步节点模板...")
r = api("POST", f"{API}/api/admin/nodes/{node_id}/sync-templates", {}, token=token)
print(f"  sync-templates: {r.status_code}")
print(f"  {r.text[:400] if r.status_code == 200 else r.text[:300]}")

# Sync VMs
print("\n[2e] 同步节点 VM...")
r = api("POST", f"{API}/api/admin/nodes/{node_id}/sync-vms", {}, token=token)
print(f"  sync-vms: {r.status_code}")

# ============================================================
print("\n" + "=" * 60)
print("🔧 阶段3: 创建 LXC 产品")
print("=" * 60)

# Check available OS templates
print("\n[3a] 检查已同步的模板...")
r = api("GET", f"{API}/api/admin/templates", token=token)
print(f"  模板列表: {r.status_code}")
data = r.json()
templates = data.get("data", {}).get("templates", data.get("data", []))
if isinstance(templates, list):
    for t in templates:
        print(f"    {t.get('name','?')[:40]} | type={t.get('type','?')}")

# Also check template listing from node
print("\n[3b] 从节点获取可用 OS 模板...")
r = api("GET", f"{API}/api/admin/nodes/{node_id}/templates", token=token)
print(f"  节点模板: {r.status_code}")
if r.status_code == 200:
    temps = r.json().get("data", {}).get("templates", r.json().get("data", []))
    if isinstance(temps, list):
        debian_temps = [t for t in temps if 'debian' in str(t).lower() or 'bookworm' in str(t).lower()]
        print(f"  Debian 模板: {len(debian_temps)}")
        for t in debian_temps[:5]:
            print(f"    {json.dumps(t)[:200]}")
    else:
        print(f"  Raw: {str(temps)[:300]}")
else:
    print(f"  {r.text[:300]}")

# Create product
print("\n[3c] 创建 LXC Debian12 产品...")
product_data = {
    "name": "LXC-Debian12-NAT",
    "type": "lxc",
    "cpu": 1,
    "memory": 512,
    "disk": 1,
    "bandwidth": 100,
    "traffic": 1000,
    "monthly_price": 9.90,
    "yearly_price": 99.00,
    "description": "LXC Debian 12 容器 - NAT4 + 独立IPv6(vmbr2)",
    "status": "active",
    "node_ids": [2],
    "os_templates": ["debian-12-standard"],
    "sort_order": 10
}
r = api("POST", f"{API}/api/admin/products", product_data, token=token)
print(f"  创建产品: {r.status_code}")
print(f"  {r.text[:500]}")

product_id = None
if r.status_code in [200, 201]:
    p = r.json().get("data", {}).get("product", r.json().get("data", {}))
    product_id = p.get("id")
    print(f"  ✅ 产品 ID: {product_id}")

print(f"\n  产品ID={product_id}")

print("\n" + "=" * 60)
print(f"✅ 准备完成! 产品ID={product_id}, 节点ID={node_id}")
print("=" * 60)
