import paramiko
import requests
import urllib3
urllib3.disable_warnings()

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect('pve.ypvps.com', port=22, username='root', password='thanks123A#', timeout=15)

def run(cmd, timeout=15):
    stdin, stdout, stderr = ssh.exec_command(cmd, timeout=timeout)
    out = stdout.read().decode('utf-8', errors='replace')
    err = stderr.read().decode('utf-8', errors='replace')
    return out.strip(), err.strip()

API = "https://pve.ypvps.com"

# 1. Login to get token
print("[1] 登录获取 token...")
r = requests.post(f"{API}/api/login",
    json={"email": "admin@pve.ypvps.com", "password": "admin123"},
    headers={"Accept": "application/json"}, timeout=10, verify=False)
token = r.json().get('data', {}).get('token', '')
print(f"  {'✅' if token else '❌'} Token: {token[:40] if token else 'NONE'}...")

headers = {"Authorization": f"Bearer {token}", "Content-Type": "application/json", "Accept": "application/json"}

# 2. Clear existing products
print("\n[2] 清理旧产品...")
r = requests.get(f"{API}/api/admin/products", headers=headers, timeout=10, verify=False)
if r.status_code == 200:
    products = r.json().get('data', {}).get('products', [])
    for p in products:
        requests.delete(f"{API}/api/admin/products/{p['id']}", headers=headers, timeout=10, verify=False)
        print(f"  🗑 {p['name']}")
    print(f"  ✅ 已清理 {len(products)} 个旧产品")

# 3. Create products
print("\n[3] 创建示例产品...")
PRODUCTS = [
    {"name": "\u5165\u95e8\u578b VPS", "type": "kvm", "cpu": 1, "memory": 1024, "disk": 20, "bandwidth": 10, "traffic": 500, "monthly_price": 19.90, "yearly_price": 199.00, "description": "\u9002\u5408\u4e2a\u4eba\u535a\u5ba2\u3001\u5c0f\u578b\u7f51\u7ad9", "status": "active", "sort_order": 1, "stock": 50, "node_ids": [1], "template_ids": [], "features": ["1 vCPU", "1 GB RAM", "20 GB SSD", "10 Mbps", "500 GB/mo"]},
    {"name": "\u6807\u51c6\u578b VPS", "type": "kvm", "cpu": 2, "memory": 2048, "disk": 40, "bandwidth": 20, "traffic": 1000, "monthly_price": 39.90, "yearly_price": 399.00, "description": "\u9002\u5408\u4e2d\u578b\u7f51\u7ad9\u3001\u7535\u5546\u5e73\u53f0", "status": "active", "sort_order": 2, "stock": 30, "node_ids": [1], "template_ids": [], "features": ["2 vCPU", "2 GB RAM", "40 GB SSD", "20 Mbps", "1000 GB/mo"]},
    {"name": "\u4e13\u4e1a\u578b VPS", "type": "kvm", "cpu": 4, "memory": 4096, "disk": 80, "bandwidth": 50, "traffic": 2000, "monthly_price": 79.90, "yearly_price": 799.00, "description": "\u9002\u5408\u9ad8\u5e76\u53d1\u7f51\u7ad9\u3001\u5e94\u7528\u670d\u52a1\u5668", "status": "active", "sort_order": 3, "stock": 20, "node_ids": [1], "template_ids": [], "features": ["4 vCPU", "4 GB RAM", "80 GB SSD", "50 Mbps", "2000 GB/mo", "Snapshot + Backup"]},
    {"name": "\u65d7\u8230\u578b VPS", "type": "kvm", "cpu": 8, "memory": 8192, "disk": 160, "bandwidth": 100, "traffic": 5000, "monthly_price": 159.90, "yearly_price": 1599.00, "description": "\u9002\u5408\u5927\u578b\u9879\u76ee\u3001\u6e38\u620f\u670d\u52a1\u5668", "status": "active", "sort_order": 4, "stock": 10, "node_ids": [1], "template_ids": [], "features": ["8 vCPU", "8 GB RAM", "160 GB NVMe", "100 Mbps", "5000 GB/mo", "DDoS Protection", "Priority Support"]},
]

for i, prod in enumerate(PRODUCTS):
    r = requests.post(f"{API}/api/admin/products", headers=headers, json=prod, timeout=10, verify=False)
    if r.status_code in (200, 201):
        pid = r.json().get('data', {}).get('product', {}).get('id', '?')
        print(f"  ✅ [{i+1}/4] {prod['name']} (ID:{pid}) \u00a5{prod['monthly_price']}/\u6708")
    else:
        print(f"  \u274c [{i+1}/4] {prod['name']}: {r.status_code} {r.text[:150]}")

# 4. Final verification
print("\n[4] \u6700\u7ec8\u9a8c\u8bc1...")

tests = [
    ("\u516c\u5f00\u4ea7\u54c1", f"{API}/api/products", None),
    ("\u516c\u5f00\u516c\u544a", f"{API}/api/announcements", None),
    ("\u7ba1\u7406\u4eea\u8868\u677f", f"{API}/api/admin/dashboard", headers),
    ("\u7ba1\u7406\u4ea7\u54c1", f"{API}/api/admin/products", headers),
    ("\u7ba1\u7406\u8282\u70b9", f"{API}/api/admin/nodes", headers),
    ("\u7ba1\u7406\u7528\u6237", f"{API}/api/admin/users", headers),
    ("\u7ba1\u7406\u8ba2\u5355", f"{API}/api/admin/orders", headers),
]

all_ok = True
for name, url, h in tests:
    try:
        r = requests.get(url, headers=h or {"Accept": "application/json"}, timeout=10, verify=False)
        ok = r.status_code == 200
        if not ok:
            all_ok = False
        print(f"  {'✅' if ok else '\u274c'} {name}: {r.status_code}")
    except Exception as e:
        print(f"  \u274c {name}: {e}")
        all_ok = False

# 5. Show products
print("\n[5] \u4ea7\u54c1\u8be6\u60c5...")
r = requests.get(f"{API}/api/products", headers={"Accept": "application/json"}, timeout=10, verify=False)
if r.status_code == 200:
    data = r.json()
    products = data.get('data', [])
    if isinstance(products, dict):
        products = products.get('products', [])
    for p in products:
        print(f"  #{p['id']} {p['name']} | {p['type'].upper()} | CPU:{p['cpu']} RAM:{p['memory']}MB DISK:{p['disk']}GB | \u00a5{p.get('monthly_price','?')}/\u6708 | {p['status']}")

ssh.close()
print(f"\n{'='*50}")
print(f"  {'✅ 全部部署完成！' if all_ok else '\u26a0 有部分 API 测试失败'}")
print(f"{'='*50}")
