#!/usr/bin/env python3
"""仅配置 Epay settings，避免长时间操作"""
import paramiko, time

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect('pve.ypvps.com', 22, 'root', 'thanks123A#')

def run(cmd, timeout=30):
    stdin, stdout, stderr = ssh.exec_command(cmd, timeout=timeout)
    return stdout.read().decode('utf-8', errors='replace').strip()

print("[1] 写入 Epay settings...")
epay_php = '''cd /var/www/pve-panel && php artisan tinker --execute="
\\App\\Models\\Setting::updateOrCreate(['key'=>'epay_api_url'],['value'=>'https://pay.wanjuanxueyi.com','type'=>'string','group'=>'payment','description'=>'Epay API URL']);
\\App\\Models\\Setting::updateOrCreate(['key'=>'epay_merchant_id'],['value'=>'2093','type'=>'string','group'=>'payment','description'=>'Epay PID']);
\\App\\Models\\Setting::updateOrCreate(['key'=>'epay_merchant_key'],['value'=>'7o6IxRTgt67ntX9nIZRx2koiPX9X2ix2','type'=>'string','group'=>'payment','description'=>'Epay KEY']);
\\App\\Models\\Setting::updateOrCreate(['key'=>'epay_notify_url'],['value'=>'https://pve.ypvps.com/api/payment/epay/notify','type'=>'string','group'=>'payment','description'=>'Notify URL']);
\\App\\Models\\Setting::updateOrCreate(['key'=>'epay_return_url'],['value'=>'https://pve.ypvps.com/user/payment/result','type'=>'string','group'=>'payment','description'=>'Return URL']);
echo 'DONE';
"'''
print(run(epay_php))

print("\n[2] 验证 Epay config...")
result = run(r'''cd /var/www/pve-panel && php artisan tinker --execute="
\$s = new \\App\\Services\\EpayService();
\\$ref = new \\ReflectionClass(\$s);
foreach (['apiUrl','merchantId','merchantKey','notifyUrl','returnUrl'] as \$p) {
    \$prop = \\$ref->getProperty(\$p);
    \$prop->setAccessible(true);
    echo \$p.': ' . \$prop->getValue(\$s) . PHP_EOL;
}
"''')
print(result)

print("\n[3] Epay 签名测试...")
print(run(r'''cd /var/www/pve-panel && php artisan tinker --execute="
\$s = new \\App\\Services\\EpayService();
\$p = ['pid'=>'2093','type'=>'alipay','out_trade_no'=>'TEST001','notify_url'=>'https://pve.ypvps.com/api/payment/epay/notify','return_url'=>'https://pve.ypvps.com','name'=>'test','money'=>'0.01','clientip'=>'127.0.0.1'];
echo 'SIGN: ' . \$s->generateSign(\$p);
"'''))

print("\n[4] 支付回调路由...")
print(run("cd /var/www/pve-panel && php artisan route:list 2>/dev/null | grep -iE 'epay|notify' | head -10"))

print("\n[5] 清缓存...")
print(run("cd /var/www/pve-panel && php artisan cache:clear && php artisan config:clear"))

ssh.close()
print("\n✅ Epay 配置完成")
