# CloudVM Pro — PVE 虚拟机销售管理平台

基于 **Laravel 11** + **Proxmox VE API** 的虚拟机销售与自动化管理平台，支持 KVM 全虚拟化和 LXC 容器。

## 功能模块

| 模块 | 说明 |
|------|------|
| 用户中心 | 注册/登录/邮箱验证/密码重置/API Key/Session 管理 |
| 产品购买 | 多套餐(KVM/LXC)选购、月付/季付/年付、优惠券 |
| VM 管理 | 开机/关机/重启/重装/快照/备份/VNC 控制台 |
| 工单系统 | 用户提交工单 → 管理员回复 → 闭环跟踪 |
| 实名认证 | 用户提交实名信息 → 后台审核 |
| 订单/账单 | 订单管理、收支流水、余额充值 |
| 公告系统 | 系统公告发布与展示 |
| 支付集成 | 易支付(Epay)对接，支持支付宝/微信 |
| 管理后台 | 用户/节点/产品/订单/VM/工单/优惠券/公告全管理 |

## 技术栈

- **后端**: Laravel 11 + MySQL 8 + Redis
- **认证**: Laravel Sanctum (API Token)
- **虚拟化**: Proxmox VE REST API (KVM + LXC)
- **前端**: 纯 HTML/CSS/JS + Three.js + Chart.js
- **支付**: 易支付 (Epay)

## 项目结构

```
pve-panel/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Api/          # 用户端 API (23个)
│   │   │   └── Admin/        # 管理端 API (11个)
│   │   ├── Middleware/       # AdminGuard, ForceJsonResponse
│   │   └── Requests/         # 表单验证 (27个)
│   ├── Models/               # Eloquent 模型 (19个)
│   ├── Services/             # 核心服务层
│   │   ├── ProxmoxService    # PVE API 交互
│   │   ├── VmService         # VM 生命周期管理
│   │   ├── OrderService      # 订单处理
│   │   └── ...
│   ├── Jobs/                 # 异步任务
│   └── Console/Commands/     # 计划任务
├── database/migrations/      # 数据迁移 (19个)
├── routes/
│   ├── api.php               # 用户端路由 (77条)
│   └── admin.php             # 管理端路由 (58条)
└── public/
    ├── index.html            # 落地页
    ├── views/                # 前端页面
    │   ├── admin/            # 管理后台 (12页)
    │   └── user/             # 用户端 (6页)
    └── assets/               # CSS/JS/Images
```

## 快速部署

### 环境要求

- PHP 8.2+
- MySQL 8.0+
- Redis (可选，用于缓存/队列/会话)
- Proxmox VE 7.x/8.x
- Composer
- Nginx/Apache

### 1. 克隆项目

```bash
git clone https://github.com/qdmz/pve-panel.git
cd pve-panel
composer install
```

### 2. 配置环境

```bash
cp .env.example .env
php artisan key:generate
```

编辑 `.env` 配置数据库、邮件、支付：

```env
APP_NAME="CloudVM Pro"
APP_URL=https://your-domain.com
APP_DEBUG=false

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=pve_panel
DB_USERNAME=your_user
DB_PASSWORD=your_password

# SMTP 邮件
MAIL_MAILER=smtp
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=user@example.com
MAIL_PASSWORD=your_password
MAIL_FROM_ADDRESS=noreply@example.com

# 易支付
EPAY_PID=your_pid
EPAY_KEY=your_key
EPAY_API_URL=https://epay.example.com/
EPAY_NOTIFY_URL=https://your-domain.com/api/payment/notify
EPAY_RETURN_URL=https://your-domain.com/pay/return
```

### 3. 初始化数据库

```bash
php artisan migrate
```

### 4. 添加 PVE 节点

在管理后台 **节点管理 → 添加节点** 中配置 Proxmox VE 连接：

| 字段 | 说明 |
|------|------|
| 名称 | 节点标识 (如 pve-main) |
| 主机 | PVE 服务器 IP/域名 |
| 端口 | 默认 8006 |
| 认证类型 | api_token (推荐) |
| API Token | `root@pam!username=UUID` |

> **PVE Token 创建**: PVE 管理界面 → Datacenter → Permissions → API Tokens → Add

### 5. 创建产品

后台 → 产品管理 → 添加产品，配置 KVM/LXC 套餐：

- CPU/内存/磁盘/带宽/流量
- 月付/季付/年付价格
- 操作系统模板
- 关联 PVE 节点

### 6. 配置 Nginx

```nginx
server {
    listen 80;
    server_name your-domain.com;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name your-domain.com;

    ssl_certificate     /etc/ssl/certs/your-domain.pem;
    ssl_certificate_key /etc/ssl/private/your-domain.key;

    root /var/www/pve-panel/public;
    index index.html index.php;

    # API 请求转发到 Laravel
    location /api/ {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # 静态文件
    location / {
        try_files $uri $uri/ /index.html;
    }

    # PHP 处理
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

### 7. 启动队列（可选）

```bash
php artisan queue:work --queue=default,pve,emails
```

### 8. 计划任务

```bash
* * * * * cd /var/www/pve-panel && php artisan schedule:run >> /dev/null 2>&1
```

## 后台管理

- 默认管理员: `admin@pve.ypvps.com` / `admin123`
- 管理后台: `https://your-domain.com/views/admin/index.html`

创建管理员用户：

```bash
php artisan tinker
>>> \App\Models\User::create(['name'=>'Admin','email'=>'admin@your.com','password'=>bcrypt('password'),'role'=>'admin','email_verified_at'=>now()])
```

## API 接口

### 认证状态标记
- 🔓 公开接口
- 🔒 需登录
- ⭐ 管理员专用

### 用户端 (77条)

| 方法 | 路径 | 说明 | 认证 |
|------|------|------|------|
| POST | /api/register | 注册 | 🔓 |
| POST | /api/login | 登录 | 🔓 |
| POST | /api/logout | 登出 | 🔒 |
| GET | /api/me | 个人信息 | 🔒 |
| GET | /api/products | 产品列表 | 🔓 |
| GET | /api/products/{product} | 产品详情 | 🔓 |
| GET | /api/orders | 订单列表 | 🔒 |
| POST | /api/orders | 创建订单 | 🔒 |
| POST | /api/orders/{order}/pay | 支付 | 🔒 |
| GET | /api/announcements | 公告列表 | 🔓 |
| GET | /api/tickets | 工单列表 | 🔒 |
| POST | /api/tickets | 创建工单 | 🔒 |
| GET | /api/billing | 账单列表 | 🔒 |
| POST | /api/recharge | 余额充值 | 🔒 |
| PUT | /api/profile | 更新资料 | 🔒 |
| PUT | /api/password | 修改密码 | 🔒 |
| POST | /api/api-key | 创建 API Key | 🔒 |

### 管理端 (58条)

| 方法 | 路径 | 说明 |
|------|------|------|
| GET | /api/admin/dashboard | 仪表板 |
| GET | /api/admin/users | 用户列表 |
| GET | /api/admin/nodes | 节点列表 |
| POST | /api/admin/nodes/{node}/test | 测试节点连接 |
| POST | /api/admin/nodes/{node}/sync-vms | 同步 VM |
| GET | /api/admin/vms | VM 列表 |
| POST | /api/admin/vms/{vm}/start | 开机 |
| POST | /api/admin/vms/{vm}/stop | 关机 |
| POST | /api/admin/vms/{vm}/restart | 重启 |
| GET | /api/admin/orders | 订单列表 |
| GET | /api/admin/tickets | 工单列表 |
| GET | /api/admin/verify | 认证审核 |

## 许可证

MIT License
