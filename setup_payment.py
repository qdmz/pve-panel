#!/usr/bin/env python3
"""一键配置：易支付 settings + SSL 加固 + 全链路验证"""
import paramiko, json, urllib3
urllib3.disable_warnings()

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect('pve.ypvps.com', 22, 'root', 'thanks123A#')

def run(cmd):
    stdin, stdout, stderr = ssh.exec_command(cmd)
    return stdout.read().decode('utf-8', errors='replace').strip()

print("=" * 60)
print("[1/5] 写入易支付配置到 settings 表")
print("=" * 60)

epay_cmd = r'''cd /var/www/pve-panel && php artisan tinker --execute="
\App\Models\Setting::updateOrCreate(['key'=>'epay_api_url'],[
    'value'=>'https://pay.wanjuanxueyi.com','type'=>'string','group'=>'payment','description'=>'易支付API地址'
]);
\App\Models\Setting::updateOrCreate(['key'=>'epay_merchant_id'],[
    'value'=>'2093','type'=>'string','group'=>'payment','description'=>'易支付商户ID'
]);
\App\Models\Setting::updateOrCreate(['key'=>'epay_merchant_key'],[
    'value'=>'7o6IxRTgt67ntX9nIZRx2koiPX9X2ix2','type'=>'string','group'=>'payment','description'=>'易支付商户密钥'
]);
\App\Models\Setting::updateOrCreate(['key'=>'epay_notify_url'],[
    'value'=>'https://pve.ypvps.com/api/payment/epay/notify','type'=>'string','group'=>'payment','description'=>'支付回调地址'
]);
\App\Models\Setting::updateOrCreate(['key'=>'epay_return_url'],[
    'value'=>'https://pve.ypvps.com/user/payment/result','type'=>'string','group'=>'payment','description'=>'支付完成跳转地址'
]);
echo 'EPAY SETTINGS DONE\n';
"'''
print(run(epay_cmd))

print("\n验证写入结果:")
print(run(r'''cd /var/www/pve-panel && php artisan tinker --execute="
\$keys=['epay_api_url','epay_merchant_id','epay_merchant_key','epay_notify_url','epay_return_url'];
foreach(\$keys as \$k){echo \$k.': '.\App\Models\Setting::getValue(\$k,'NULL').PHP_EOL;}
"'''))

print("\n" + "=" * 60)
print("[2/5] 验证易支付签名算法")
print("=" * 60)
# Test: create an order via API and check Epay sign generation
print(run(r'''cd /var/www/pve-panel && php artisan tinker --execute="
\$s = new \App\Services\EpayService();
\$params = ['pid'=>2093,'type'=>'alipay','out_trade_no'=>'TEST'.time(),'notify_url'=>'https://pve.ypvps.com/api/payment/epay/notify','return_url'=>'https://pve.ypvps.com','name'=>'测试商品','money'=>'0.01','clientip'=>'127.0.0.1'];
\$sign = \$s->generateSign(\$params);
echo 'SIGN: '.\$sign.PHP_EOL;
echo 'API_URL: '.(\$s->apiUrl ?? 'N/A').PHP_EOL;
echo 'MERCHANT_ID: '.(\$s->merchantId ?? 'N/A').PHP_EOL;
"'''))

print("\n" + "=" * 60)
print("[3/5] 检查支付回调路由注册")
print("=" * 60)
print(run("cd /var/www/pve-panel && php artisan route:list 2>/dev/null | grep -i epay"))
print(run("cd /var/www/pve-panel && php artisan route:list 2>/dev/null | grep -i 'payment\|notify'"))

print("\n" + "=" * 60)
print("[4/5] SSL 自动续期加固")
print("=" * 60)
# Test certbot renew --dry-run
print("--- certbot renew dry-run ---")
print(run("certbot renew --dry-run 2>&1 | tail -10"))

# Verify nginx reload hook
print("\n--- nginx reload test ---")
print(run("nginx -t 2>&1 && echo 'NGINX_CONFIG_OK' || echo 'NGINX_CONFIG_ERROR'"))

# Test the deploy hook manually
print("\n--- test renew hook ---")
print(run("systemctl reload nginx 2>&1 && echo 'RELOAD_OK' || echo 'RELOAD_FAILED'"))

# Verify cert expiry
print("\n--- cert expiry ---")
print(run("openssl x509 -enddate -noout -in /etc/letsencrypt/live/pve.ypvps.com/fullchain.pem"))

print("\n" + "=" * 60)
print("[5/5] 验证支付回调端点可达")
print("=" * 60)
# Test the notify endpoint (should return error about missing signature, not 404/500)
import requests
try:
    r = requests.post('https://pve.ypvps.com/api/payment/epay/notify', 
        data={'test': '1'}, timeout=10, verify=False,
        headers={'User-Agent': 'EpayNotify/1.0'})
    print(f"Notify endpoint: HTTP {r.status_code}")
    print(f"Response: {r.text[:200]}")
except Exception as e:
    print(f"Notify endpoint ERROR: {e}")

# Also test the settings API endpoint for admin
try:
    r = requests.get('https://pve.ypvps.com/api/payment/epay/notify', timeout=10, verify=False)
    print(f"Notify GET: HTTP {r.status_code}")
except:
    pass

ssh.close()
print("\n✅ 配置完成")
