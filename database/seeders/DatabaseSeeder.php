<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Create admin user
        $admin = User::firstOrCreate(
            ['email' => 'admin@pve.ypvps.com'],
            [
                'name'              => 'Admin',
                'password'          => Hash::make('admin123'),
                'role'              => 'admin',
                'status'            => 'active',
                'email_verified_at' => now(),
                'verification_status' => 'verified',
            ]
        );

        $this->command->info("Admin user created: admin@pve.ypvps.com / admin123");

        // Seed default settings
        $defaultSettings = [
            ['key' => 'site_name', 'value' => 'CloudVM Pro', 'type' => 'string', 'group' => 'general', 'description' => '站点名称'],
            ['key' => 'site_description', 'value' => 'PVE 虚拟机销售管理平台', 'type' => 'string', 'group' => 'general', 'description' => '站点描述'],
            ['key' => 'currency', 'value' => 'CNY', 'type' => 'string', 'group' => 'general', 'description' => '货币'],
            ['key' => 'default_bandwidth', 'value' => '100', 'type' => 'integer', 'group' => 'vm', 'description' => '默认带宽 (Mbps)'],
            ['key' => 'max_backups', 'value' => '5', 'type' => 'integer', 'group' => 'backup', 'description' => '最大备份数'],
            ['key' => 'backup_retention_days', 'value' => '30', 'type' => 'integer', 'group' => 'backup', 'description' => '备份保留天数'],
            ['key' => 'epay_api_url', 'value' => '', 'type' => 'string', 'group' => 'payment', 'description' => '易支付 API 地址'],
            ['key' => 'epay_merchant_id', 'value' => '', 'type' => 'string', 'group' => 'payment', 'description' => '易支付商户ID'],
            ['key' => 'epay_merchant_key', 'value' => '', 'type' => 'string', 'group' => 'payment', 'description' => '易支付商户密钥'],
            ['key' => 'epay_notify_url', 'value' => '', 'type' => 'string', 'group' => 'payment', 'description' => '易支付通知地址'],
            ['key' => 'epay_return_url', 'value' => '', 'type' => 'string', 'group' => 'payment', 'description' => '易支付返回地址'],
            ['key' => 'smtp_host', 'value' => 'smtp.qq.com', 'type' => 'string', 'group' => 'mail', 'description' => 'SMTP 服务器'],
            ['key' => 'smtp_port', 'value' => '465', 'type' => 'integer', 'group' => 'mail', 'description' => 'SMTP 端口'],
            ['key' => 'smtp_username', 'value' => '', 'type' => 'string', 'group' => 'mail', 'description' => 'SMTP 用户名'],
            ['key' => 'smtp_password', 'value' => '', 'type' => 'string', 'group' => 'mail', 'description' => 'SMTP 密码'],
            ['key' => 'smtp_encryption', 'value' => 'ssl', 'type' => 'string', 'group' => 'mail', 'description' => 'SMTP 加密方式'],
            ['key' => 'smtp_from_address', 'value' => '', 'type' => 'string', 'group' => 'mail', 'description' => '发件人地址'],
            ['key' => 'smtp_from_name', 'value' => 'CloudVM Pro', 'type' => 'string', 'group' => 'mail', 'description' => '发件人名称'],
        ];

        foreach ($defaultSettings as $setting) {
            Setting::firstOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }

        $this->command->info('Default settings seeded.');
    }
}
