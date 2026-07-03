#!/usr/bin/env python3
"""最终验证和修复"""
import paramiko, requests, urllib3, json
urllib3.disable_warnings()

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect('pve.ypvps.com', 22, 'root', 'thanks123A#')

def run(cmd, timeout=30):
    stdin, stdout, stderr = ssh.exec_command(cmd, timeout=timeout)
    return stdout.read().decode('utf-8', errors='replace').strip()

# Check test-epay 500 error
print("=" * 60)
print("[A] test-epay 500 错误排查")
print("=" * 60)
print("Laravel log:")
print(run("tail -30 /var/www/pve-panel/storage/logs/laravel.log 2>/dev/null | grep -A5 'test-epay\|Setting\|Epay' | tail -20"))

# Check SettingController
print("\nSettingController testEpay:")
print(run("cd /var/www/pve-panel && grep -n 'testEpay\|test-epay\|testEp' app/Http/Controllers/Admin/SettingController.php"))

# Try the test again with a simple PHP test directly
print("\n直接测试 EpayService:")
print(run(r'''cd /var/www/pve-panel && php artisan tinker --execute="
\$s = new \\App\\Services\\EpayService();
\$r = \$s->createOrder(
    \\App\\Models\\Order::first() ?? new \\App\\Models\\Order(['order_no'=>'TEST-DIRECT','amount'=>0.01,'user_id'=>1]),
    'alipay'
);
echo 'CREATE_ORDER: ' . json_encode(['success'=>\$r['success']??false,'pay_url'=>substr(\$r['pay_url']??'NONE',0,50)]);
"'''))

# ==================== B. SSL renewal test ====================
print("\n\n" + "=" * 60)
print("[B] SSL 续期验证")
print("=" * 60)

# Kill any stuck certbot process
run("pkill -f 'certbot renew' 2>/dev/null; sleep 1; echo 'cleaned'")

# Test with explicit path and ensure hook works
print("Certbot renew --dry-run (force):")
print(run("timeout 45 certbot renew --dry-run --force-renewal --cert-name pve.ypvps.com 2>&1 | tail -8"))

# Verify the deploy hook (systemctl reload nginx)
print("\nHook 测试:")
print(run("if systemctl reload nginx 2>&1; then echo 'NGINX_RELOAD_OK'; else echo 'NGINX_RELOAD_FAIL'; fi"))

# ==================== C. Final full verification ====================
print("\n\n" + "=" * 60)
print("[C] 最终全链路验证")
print("=" * 60)

results = {}

# 1. Front page
try:
    r = requests.get('https://pve.ypvps.com/', timeout=10, verify=False)
    results['frontend'] = f"HTTP {r.status_code}"
except Exception as e:
    results['frontend'] = f"ERROR: {str(e)[:50]}"

# 2. API products
try:
    r = requests.get('https://pve.ypvps.com/api/products', timeout=10, verify=False,
        headers={'Accept': 'application/json'})
    data = r.json()
    count = len(data.get('data', [])) if isinstance(data.get('data'), list) else len(data.get('data', {}).get('products', []))
    results['api_products'] = f"HTTP {r.status_code}, {count} products"
except Exception as e:
    results['api_products'] = f"ERROR: {str(e)[:50]}"

# 3. Login
try:
    r = requests.post('https://pve.ypvps.com/api/login',
        json={'email': 'admin@pve.ypvps.com', 'password': 'admin123'},
        timeout=10, verify=False,
        headers={'Accept': 'application/json'})
    if r.status_code == 200:
        token = r.json().get('data', {}).get('token', '')
        results['login'] = f"HTTP {r.status_code}, token={token[:15]}..."
    else:
        results['login'] = f"HTTP {r.status_code}"
except Exception as e:
    results['login'] = f"ERROR: {str(e)[:50]}"

# 4. Callback endpoint
try:
    r = requests.post('https://pve.ypvps.com/api/payment/notify',
        data={'test': '1'}, timeout=10, verify=False,
        headers={'User-Agent': 'EpayNotify/1.0'})
    results['callback'] = f"HTTP {r.status_code} (expect 'fail' for invalid sig)"
except Exception as e:
    results['callback'] = f"ERROR: {str(e)[:50]}"

# 5. SSL cert expiry
expiry = run("openssl x509 -enddate -noout -in /etc/letsencrypt/live/pve.ypvps.com/fullchain.pem 2>/dev/null")
results['ssl_expiry'] = expiry

for k, v in results.items():
    print(f"  {k:15s} → {v}")

ssh.close()
print("\n✅ 诊断完成")
