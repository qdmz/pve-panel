#!/usr/bin/env python3
"""全面诊断：易支付 / SMTP / SSL 状态"""
import paramiko, json

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect('pve.ypvps.com', 22, 'root', 'thanks123A#')

def run(cmd):
    stdin, stdout, stderr = ssh.exec_command(cmd)
    return stdout.read().decode('utf-8', errors='replace').strip()

print("=" * 60)
print("1. 易支付 Epay 配置（数据库 settings 表）")
print("=" * 60)
print(run(r'''cd /var/www/pve-panel && php artisan tinker --execute="
\$keys = ['epay_api_url','epay_merchant_id','epay_merchant_key','epay_notify_url','epay_return_url'];
foreach (\$keys as \$k) { \$s = \App\Models\Setting::where('key',\$k)->first(); echo \$k.': '.(\$s?\$s->value:'NULL').PHP_EOL; }
"'''))

print("\n" + "=" * 60)
print("2. SMTP 邮件配置（.env）")
print("=" * 60)
print(run("grep -E '^MAIL_' /var/www/pve-panel/.env | sed 's/=.*/=[HIDDEN]/'"))

print("\n" + "=" * 60)
print("3. SSL 证书状态")
print("=" * 60)
print(run("openssl s_client -connect pve.ypvps.com:443 -servername pve.ypvps.com </dev/null 2>/dev/null | openssl x509 -noout -dates -subject -issuer 2>/dev/null"))
print("--- certbot ---")
print(run("which certbot 2>/dev/null && certbot certificates 2>/dev/null || echo 'certbot not found'"))
print("--- acme.sh ---")
print(run("ls ~/.acme.sh/pve.ypvps.com*/pve.ypvps.com.cer 2>/dev/null && echo 'acme.sh cert found' || echo 'acme.sh cert not found'"))
print("--- cron ---")
print(run("crontab -l 2>/dev/null | grep -iE 'certbot|acme|ssl|renew' || echo 'No SSL cron jobs'"))

print("\n" + "=" * 60)
print("4. 邮件发送测试")
print("=" * 60)
print(run(r'''cd /var/www/pve-panel && php artisan tinker --execute="
try { \Mail::raw('Test email from pve.ypvps.com', function(\$msg) { \$msg->to('qdmz@vip.qq.com')->subject('PVE Panel SMTP Test'); }); echo 'MAIL OK'; }
catch(\Exception \$e) { echo 'MAIL FAIL: '.\$e->getMessage(); }
"'''))

print("\n" + "=" * 60)
print("5. Nginx SSL 配置")
print("=" * 60)
print(run("grep -n 'ssl_certificate\|listen 443\|server_name' /etc/nginx/sites-enabled/pvecloud 2>/dev/null | head -10"))

print("\n" + "=" * 60)
print("6. Laravel 缓存 & 队列状态")
print("=" * 60)
print(run("cd /var/www/pve-panel && php artisan optimize:clear 2>&1 | tail -3"))

ssh.close()
