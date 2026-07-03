#!/usr/bin/env python3
"""快速最终验证 - 跳过长时间操作"""
import paramiko, requests, urllib3
urllib3.disable_warnings()

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect('pve.ypvps.com', 22, 'root', 'thanks123A#')

def run(cmd, timeout=15):
    stdin, stdout, stderr = ssh.exec_command(cmd, timeout=timeout)
    return stdout.read().decode('utf-8', errors='replace').strip()

# 1. Check testEpay method
print("=" * 60)
print("[1] SettingController::testEpay 源码")
print("=" * 60)
print(run("cd /var/www/pve-panel && sed -n '79,100p' app/Http/Controllers/Admin/SettingController.php"))

# 2. Quick SSL check only
print("\n" + "=" * 60)
print("[2] SSL 状态速查")
print("=" * 60)
print(run("echo '--- CERT ---' && openssl x509 -enddate -noout -in /etc/letsencrypt/live/pve.ypvps.com/fullchain.pem"))
print(run("echo '--- CRON ---' && crontab -l 2>/dev/null | grep cert"))
print(run("echo '--- NGINX ---' && nginx -t 2>&1 | tail -1"))

# 3. Full API verification without long ops
print("\n" + "=" * 60)
print("[3] 全端点验证")
print("=" * 60)

results = {}
checks = [
    ('首页', 'GET', 'https://pve.ypvps.com/', None),
    ('产品列表', 'GET', 'https://pve.ypvps.com/api/products', {'Accept':'application/json'}),
    ('公告列表', 'GET', 'https://pve.ypvps.com/api/announcements', {'Accept':'application/json'}),
    ('支付回调', 'POST', 'https://pve.ypvps.com/api/payment/notify', {'User-Agent':'EpayNotify/1.0'}),
    ('登录', 'POST', 'https://pve.ypvps.com/api/login', {'Accept':'application/json'}),
]

for name, method, url, headers in checks:
    try:
        if method == 'GET':
            r = requests.get(url, timeout=10, verify=False, headers=headers or {})
        else:
            body = {'email':'admin@pve.ypvps.com','password':'admin123'} if 'login' in url else {'test':'1'}
            r = requests.post(url, timeout=10, verify=False, headers=headers or {}, json=body if 'login' in url else body)
        print(f"  {name:10s} → HTTP {r.status_code} ({len(r.text)} bytes)")
    except Exception as e:
        print(f"  {name:10s} → ERROR: {str(e)[:40]}")

# 4. Epay full flow test with admin token
print("\n" + "=" * 60)
print("[4] 易支付全链路（管理后台 test-epay）")
print("=" * 60)
try:
    # Login
    r = requests.post('https://pve.ypvps.com/api/login',
        json={'email':'admin@pve.ypvps.com','password':'admin123'},
        timeout=10, verify=False, headers={'Accept':'application/json'})
    token = r.json().get('data',{}).get('token','')
    
    # Get admin settings
    r2 = requests.get('https://pve.ypvps.com/api/admin/settings',
        timeout=10, verify=False,
        headers={'Authorization':f'Bearer {token}','Accept':'application/json'})
    print(f"Admin settings: HTTP {r2.status_code}")
    if r2.status_code == 200:
        data = r2.json()
        print(f"Response keys: {list(data.keys()) if isinstance(data, dict) else type(data)}")
    
    # Try test-epay
    r3 = requests.post('https://pve.ypvps.com/api/admin/settings/test-epay',
        json={}, timeout=10, verify=False,
        headers={'Authorization':f'Bearer {token}','Accept':'application/json'})
    print(f"Test Epay: HTTP {r3.status_code}")
    print(f"Body: {r3.text[:300]}")
except Exception as e:
    print(f"ERROR: {e}")

# 5. Check auth routes
print("\n" + "=" * 60)
print("[5] 认证路由")
print("=" * 60)
print(run("cd /var/www/pve-panel && php artisan route:list 2>/dev/null | grep -E 'login|register|verify' | head -8"))

ssh.close()
print("\n✅ 完成")
