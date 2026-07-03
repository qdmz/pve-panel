/**
 * CloudVM Pro — Local Development Server
 * Serves static frontend + mock API backend
 */
const express = require('express');
const cors = require('cors');
const path = require('path');

const app = express();
const PORT = 3000;

app.use(cors());
app.use(express.json());
app.use(express.urlencoded({ extended: true }));

// ======================================================
// STATIC FILES — serve public/ directory
// ======================================================
app.use(express.static(path.join(__dirname, 'public'), {
  extensions: ['html'],
}));

// ======================================================
// MOCK DATA
// ======================================================
const now = () => new Date().toISOString();

const mockUser = {
  id: 1,
  name: '测试用户',
  email: 'qdmz@vip.qq.com',
  balance: 520.00,
  verified: true,
  created_at: '2026-01-15T08:00:00Z',
  role: 'user',
};

const mockAdmin = {
  id: 0,
  name: '管理员',
  email: 'admin@cloudvm.pro',
  balance: 0,
  verified: true,
  created_at: '2026-01-01T08:00:00Z',
  role: 'admin',
};

const nodes = [
  { id: 1, name: 'pve', hostname: 'pve.ypvps.com', ip: '38.76.178.60', status: 'online', cpu_total: 16, cpu_used: 6, memory_total: 32768, memory_used: 18432, disk_total: 107, disk_used: 75, bridge: 'vmbr0', nat_bridge: 'vmbr1', ipv6_bridge: 'vmbr2', nat_network: '172.16.1.0/24', storage: 'local-lvm', location: '洛杉矶', region: 'us-west' },
  { id: 2, name: 'pve-hk', hostname: 'hk.cloudvm.pro', ip: '103.123.45.67', status: 'online', cpu_total: 32, cpu_used: 12, memory_total: 65536, memory_used: 28672, disk_total: 500, disk_used: 210, bridge: 'vmbr0', nat_bridge: 'vmbr1', ipv6_bridge: 'vmbr2', nat_network: '172.16.2.0/24', storage: 'local-lvm', location: '香港', region: 'ap-east' },
  { id: 3, name: 'pve-sg', hostname: 'sg.cloudvm.pro', ip: '45.67.89.10', status: 'maintenance', cpu_total: 24, cpu_used: 0, memory_total: 49152, memory_used: 0, disk_total: 300, disk_used: 0, bridge: 'vmbr0', nat_bridge: 'vmbr1', ipv6_bridge: 'vmbr2', nat_network: '172.16.3.0/24', storage: 'local-lvm', location: '新加坡', region: 'ap-se' },
];

const products = [
  { id: 1, name: '入门型', type: 'kvm', cpu: 1, memory: 1024, disk: 20, bandwidth: 5, traffic_limit: 500, price_monthly: 19.9, price_yearly: 199, status: 'active', sort: 1, description: '适合个人博客、小型网站', features: ['1核CPU', '1GB内存', '20GB SSD', '5Mbps带宽', '500GB月流量', '1个IPv4 NAT', '1个IPv6地址'] },
  { id: 2, name: '基础型', type: 'kvm', cpu: 2, memory: 2048, disk: 40, bandwidth: 10, traffic_limit: 1000, price_monthly: 39.9, price_yearly: 399, status: 'active', sort: 2, description: '适合中小企业网站、应用', features: ['2核CPU', '2GB内存', '40GB SSD', '10Mbps带宽', '1000GB月流量', '1个IPv4 NAT', '1个IPv6地址'] },
  { id: 3, name: '进阶型', type: 'kvm', cpu: 4, memory: 4096, disk: 80, bandwidth: 20, traffic_limit: 2000, price_monthly: 79.9, price_yearly: 799, status: 'active', sort: 3, description: '适合大型网站、数据库', features: ['4核CPU', '4GB内存', '80GB SSD', '20Mbps带宽', '2000GB月流量', '1个IPv4 NAT', '1个IPv6地址'] },
  { id: 4, name: '专业型', type: 'kvm', cpu: 8, memory: 8192, disk: 160, bandwidth: 50, traffic_limit: 5000, price_monthly: 159.9, price_yearly: 1599, status: 'active', sort: 4, description: '适合高并发应用、集群', features: ['8核CPU', '8GB内存', '160GB SSD', '50Mbps带宽', '5000GB月流量', '1个IPv4 NAT', '1个IPv6地址'] },
  { id: 5, name: 'LXC入门', type: 'lxc', cpu: 1, memory: 512, disk: 10, bandwidth: 5, traffic_limit: 300, price_monthly: 9.9, price_yearly: 99, status: 'active', sort: 5, description: '轻量容器，适合开发测试', features: ['1核CPU', '512MB内存', '10GB磁盘', '5Mbps带宽', '300GB月流量', '1个IPv4 NAT', '1个IPv6地址'] },
];

const vms = [
  { id: 1, vm_id: 100, user_id: 1, node_id: 1, product_id: 2, name: 'web-server-01', hostname: 'web-server-01', type: 'kvm', status: 'running', cpu: 2, memory: 2048, disk: 40, bandwidth: 10, traffic_limit: 1000, traffic_used: 234.5, ip: '172.16.1.10', nat_ipv4: '172.16.1.10', ipv6_address: '2402:4e00:1020:1400::1001', os_template: 'ubuntu-22.04-cloudinit', root_password: '********', expires_at: '2026-08-15T08:00:00Z', created_at: '2026-06-15T08:00:00Z' },
  { id: 2, vm_id: 101, user_id: 1, node_id: 1, product_id: 3, name: 'db-server-01', hostname: 'db-server-01', type: 'kvm', status: 'running', cpu: 4, memory: 4096, disk: 80, bandwidth: 20, traffic_limit: 2000, traffic_used: 567.8, ip: '172.16.1.11', nat_ipv4: '172.16.1.11', ipv6_address: '2402:4e00:1020:1400::1002', os_template: 'debian-12-cloudinit', root_password: '********', expires_at: '2026-09-01T08:00:00Z', created_at: '2026-06-20T08:00:00Z' },
  { id: 3, vm_id: 102, user_id: 1, node_id: 2, product_id: 5, name: 'dev-container', hostname: 'dev-container', type: 'lxc', status: 'stopped', cpu: 1, memory: 512, disk: 10, bandwidth: 5, traffic_limit: 300, traffic_used: 45.2, ip: '172.16.2.10', nat_ipv4: '172.16.2.10', ipv6_address: '2402:4e00:1020:1401::1001', os_template: 'ubuntu-22.04-standard_22.04-1_amd64.tar.zst', root_password: '********', expires_at: '2026-07-15T08:00:00Z', created_at: '2026-06-01T08:00:00Z' },
];

let vmIdCounter = 103;
const natRules = [];
let natRuleId = 1;
const orders = [
  { id: 1, user_id: 1, product_id: 2, vm_id: 1, amount: 39.9, status: 'paid', payment_method: 'epay', paid_at: '2026-06-15T08:05:00Z', created_at: '2026-06-15T08:00:00Z', expires_at: '2026-08-15T08:00:00Z' },
  { id: 2, user_id: 1, product_id: 3, vm_id: 2, amount: 79.9, status: 'paid', payment_method: 'epay', paid_at: '2026-06-20T08:05:00Z', created_at: '2026-06-20T08:00:00Z', expires_at: '2026-09-01T08:00:00Z' },
  { id: 3, user_id: 1, product_id: 5, vm_id: 3, amount: 9.9, status: 'paid', payment_method: 'epay', paid_at: '2026-06-01T08:05:00Z', created_at: '2026-06-01T08:00:00Z', expires_at: '2026-07-15T08:00:00Z' },
];
let orderIdCounter = 4;
const tickets = [];
let ticketIdCounter = 1;
const announcements = [
  { id: 1, title: '洛杉矶节点升级维护通知', content: '我们将于2026年7月5日凌晨2:00-4:00对洛杉矶节点进行升级维护，期间该节点的虚拟机可能会出现短暂网络中断。', type: 'maintenance', pinned: true, status: 'published', created_at: '2026-06-28T10:00:00Z' },
  { id: 2, title: '新上架香港节点！CN2 GIA线路', content: 'CloudVM Pro 香港节点正式上线！采用CN2 GIA优质线路，中国大陆延迟低至30ms。立即开通享受新品特惠！', type: 'news', pinned: true, status: 'published', created_at: '2026-06-25T14:00:00Z' },
  { id: 3, title: '暑期特惠活动开启', content: '暑假期间所有产品8折优惠，新用户首月立减50元！', type: 'promotion', pinned: false, status: 'published', created_at: '2026-06-20T09:00:00Z' },
];

// Auth token store
const tokens = {};

// ======================================================
// AUTH MIDDLEWARE (simple token check)
// ======================================================
function auth(req, res, next) {
  const token = (req.headers.authorization || '').replace('Bearer ', '');
  if (!token || !tokens[token]) {
    return res.status(401).json({ success: false, message: 'Unauthorized' });
  }
  req.user = tokens[token].role === 'admin' ? mockAdmin : mockUser;
  next();
}

function adminAuth(req, res, next) {
  const token = (req.headers.authorization || '').replace('Bearer ', '');
  if (!token || tokens[token]?.role !== 'admin') {
    return res.status(403).json({ success: false, message: 'Forbidden' });
  }
  req.user = mockAdmin;
  next();
}

// ======================================================
// API ROUTES — Public
// ======================================================
const api = express.Router();

// --- Auth ---
api.post('/register', (req, res) => {
  const token = 'token-user-' + Date.now();
  tokens[token] = { role: 'user', user: mockUser };
  res.json({ success: true, data: { token, user: mockUser }, message: '注册成功' });
});

api.post('/login', (req, res) => {
  const token = 'token-user-' + Date.now();
  tokens[token] = { role: 'user', user: mockUser };
  res.json({ success: true, data: { token, user: mockUser }, message: '登录成功' });
});

api.post('/forgot-password', (req, res) => {
  res.json({ success: true, message: '重置链接已发送到您的邮箱' });
});

api.post('/reset-password', (req, res) => {
  res.json({ success: true, message: '密码已重置' });
});

api.get('/verify-email/:token', (req, res) => {
  res.json({ success: true, message: '邮箱验证成功' });
});

api.get('/payment/notify', (req, res) => {
  res.send('success');
});

api.post('/payment/notify', (req, res) => {
  res.send('success');
});

// ======================================================
// API ROUTES — Authenticated (User)
// ======================================================
api.use(auth);

api.post('/logout', (req, res) => {
  const token = (req.headers.authorization || '').replace('Bearer ', '');
  delete tokens[token];
  res.json({ success: true, message: '已登出' });
});

api.get('/me', (req, res) => {
  res.json({ success: true, data: { user: mockUser } });
});

api.post('/resend-verification', (req, res) => {
  res.json({ success: true, message: '验证邮件已重新发送' });
});

// Profile
api.put('/profile', (req, res) => {
  Object.assign(mockUser, req.body);
  res.json({ success: true, data: { user: mockUser }, message: '资料已更新' });
});

api.put('/password', (req, res) => {
  res.json({ success: true, message: '密码已修改' });
});

api.get('/login-logs', (req, res) => {
  res.json({ success: true, data: { logs: [
    { ip: '192.168.1.1', location: '中国广东', user_agent: 'Chrome', created_at: now() },
    { ip: '10.0.0.1', location: '中国上海', user_agent: 'Firefox', created_at: '2026-06-30T12:00:00Z' },
  ] } });
});

api.put('/notifications', (req, res) => {
  res.json({ success: true, message: '通知设置已更新' });
});

api.post('/api-key', (req, res) => {
  res.json({ success: true, data: { api_key: 'cvmp-' + Math.random().toString(36).substring(2, 18) }, message: 'API密钥已生成' });
});

api.delete('/api-key', (req, res) => {
  res.json({ success: true, message: 'API密钥已删除' });
});

// VMs
api.get('/vms', (req, res) => {
  const userVms = vms.filter(v => v.user_id === req.user.id);
  res.json({ success: true, data: { data: userVms } });
});

api.get('/vms/:vm', (req, res) => {
  const vm = vms.find(v => v.id === parseInt(req.params.vm));
  if (!vm) return res.status(404).json({ success: false, message: 'VM not found' });
  res.json({ success: true, data: vm });
});

api.post('/vms/:vm/start', (req, res) => {
  const vm = vms.find(v => v.id === parseInt(req.params.vm));
  if (vm) vm.status = 'running';
  res.json({ success: true, message: '开机指令已发送' });
});

api.post('/vms/:vm/stop', (req, res) => {
  const vm = vms.find(v => v.id === parseInt(req.params.vm));
  if (vm) vm.status = 'stopped';
  res.json({ success: true, message: '关机指令已发送' });
});

api.post('/vms/:vm/restart', (req, res) => {
  res.json({ success: true, message: '重启指令已发送' });
});

api.post('/vms/:vm/reset-password', (req, res) => {
  const vm = vms.find(v => v.id === parseInt(req.params.vm));
  if (vm) vm.root_password = 'NewP@ss' + Math.random().toString(36).substring(2, 8);
  res.json({ success: true, message: '密码已重置' });
});

api.post('/vms/:vm/reinstall', (req, res) => {
  res.json({ success: true, message: '重装指令已发送，请等待几分钟' });
});

api.get('/vms/:vm/vnc', (req, res) => {
  res.json({ success: true, data: { vnc_url: 'https://pve.ypvps.com:8006/?console=kvm&vmid=' + req.params.vm } });
});

api.get('/vms/:vm/metrics', (req, res) => {
  res.json({ success: true, data: {
    cpu: Math.floor(Math.random() * 40 + 5),
    memory: Math.floor(Math.random() * 60 + 10),
    disk: Math.floor(Math.random() * 40 + 20),
    network_in: Math.floor(Math.random() * 500 + 100),
    network_out: Math.floor(Math.random() * 300 + 50),
  }});
});

api.post('/vms/:vm/renew', (req, res) => {
  res.json({ success: true, message: '续费成功' });
});

api.delete('/vms/:vm', (req, res) => {
  const idx = vms.findIndex(v => v.id === parseInt(req.params.vm));
  if (idx !== -1) vms.splice(idx, 1);
  res.json({ success: true, message: '虚拟机已销毁' });
});

// Snapshots
api.get('/vms/:vm/snapshots', (req, res) => {
  res.json({ success: true, data: { snapshots: [
    { name: 'snap-before-update', created_at: '2026-06-28T10:00:00Z' },
    { name: 'snap-initial', created_at: '2026-06-15T08:30:00Z' },
  ] } });
});

api.post('/vms/:vm/snapshots', (req, res) => {
  res.json({ success: true, message: '快照创建中...' });
});

api.delete('/vms/:vm/snapshots/:snapshot', (req, res) => {
  res.json({ success: true, message: '快照已删除' });
});

api.post('/vms/:vm/snapshots/:snapshot/restore', (req, res) => {
  res.json({ success: true, message: '快照恢复指令已发送' });
});

// NAT Rules
api.get('/vms/:vm/nat-rules', (req, res) => {
  const vmNatRules = natRules.filter(r => r.vm_id === parseInt(req.params.vm));
  res.json({ success: true, data: vmNatRules });
});

api.post('/vms/:vm/nat-rules', (req, res) => {
  const rule = { id: natRuleId++, vm_id: parseInt(req.params.vm), ...req.body, created_at: now() };
  natRules.push(rule);
  res.json({ success: true, data: { nat_rule: rule }, message: 'NAT规则已添加' });
});

api.delete('/vms/:vm/nat-rules/:natRule', (req, res) => {
  const idx = natRules.findIndex(r => r.id === parseInt(req.params.natRule));
  if (idx !== -1) natRules.splice(idx, 1);
  res.json({ success: true, message: 'NAT规则已删除' });
});

// Domains
api.get('/vms/:vm/domains', (req, res) => {
  res.json({ success: true, data: { domains: [] } });
});

api.post('/vms/:vm/domains', (req, res) => {
  res.json({ success: true, message: '域名绑定成功' });
});

api.delete('/vms/:vm/domains/:domain', (req, res) => {
  res.json({ success: true, message: '域名解绑成功' });
});

// Products
api.get('/products', (req, res) => {
  res.json({ success: true, data: { data: products.filter(p => p.status === 'active') } });
});

api.get('/products/:product', (req, res) => {
  const p = products.find(p => p.id === parseInt(req.params.product));
  if (!p) return res.status(404).json({ success: false, message: 'Product not found' });
  res.json({ success: true, data: p });
});

// Orders
api.get('/orders', (req, res) => {
  const userOrders = orders.filter(o => o.user_id === req.user.id);
  res.json({ success: true, data: { data: userOrders } });
});

api.post('/orders', (req, res) => {
  const product = products.find(p => p.id === req.body.product_id);
  if (!product) return res.status(404).json({ success: false, message: 'Product not found' });

  const order = {
    id: orderIdCounter++,
    user_id: req.user.id,
    product_id: product.id,
    amount: product.price_monthly,
    status: 'pending',
    payment_method: 'epay',
    created_at: now(),
    expires_at: null,
  };
  orders.push(order);
  res.json({ success: true, data: { order, payment_url: 'https://pay.wanjuanxueyi.com/submit.php?pid=2093&type=alipay&out_trade_no=CVMP' + order.id + '&name=' + encodeURIComponent(product.name) + '&money=' + product.price_monthly } });
});

api.get('/orders/:order', (req, res) => {
  const order = orders.find(o => o.id === parseInt(req.params.order));
  if (!order) return res.status(404).json({ success: false, message: 'Order not found' });
  res.json({ success: true, data: order });
});

api.post('/orders/:order/pay', (req, res) => {
  const order = orders.find(o => o.id === parseInt(req.params.order));
  if (order) order.status = 'paid';
  // Auto-create VM on payment
  const product = products.find(p => p.id === order.product_id);
  const newVm = {
    id: vms.length + 1,
    vm_id: vmIdCounter++,
    user_id: req.user.id,
    node_id: 1,
    product_id: order.product_id,
    name: (product?.name || 'vm') + '-' + Math.floor(Math.random() * 9000 + 1000),
    hostname: (product?.name || 'vm').toLowerCase() + '-' + Math.floor(Math.random() * 900 + 100),
    type: product?.type || 'kvm',
    status: 'running',
    cpu: product?.cpu || 1,
    memory: product?.memory || 1024,
    disk: product?.disk || 20,
    bandwidth: product?.bandwidth || 5,
    traffic_limit: product?.traffic_limit || 500,
    traffic_used: 0,
    ip: '172.16.1.' + (10 + vms.length),
    nat_ipv4: '172.16.1.' + (10 + vms.length),
    ipv6_address: '2402:4e00:1020:1400::' + (1000 + vmIdCounter),
    os_template: 'ubuntu-22.04-cloudinit',
    root_password: '********',
    expires_at: new Date(Date.now() + 30 * 24 * 3600 * 1000).toISOString(),
    created_at: now(),
  };
  vms.push(newVm);
  order.vm_id = newVm.id;
  res.json({ success: true, message: '支付成功！虚拟机已开通', data: { order, vm: newVm } });
});

api.post('/orders/:order/cancel', (req, res) => {
  const order = orders.find(o => o.id === parseInt(req.params.order));
  if (order) order.status = 'cancelled';
  res.json({ success: true, message: '订单已取消' });
});

// Payments
api.post('/recharge', (req, res) => {
  res.json({ success: true, data: { payment_url: 'https://pay.wanjuanxueyi.com/submit.php?pid=2093&type=alipay&name=账户充值&money=' + req.body.amount } });
});

// Tickets
api.get('/tickets', (req, res) => {
  res.json({ success: true, data: { data: tickets } });
});

api.post('/tickets', (req, res) => {
  const ticket = { id: ticketIdCounter++, user_id: req.user.id, ...req.body, status: 'open', priority: 'normal', created_at: now() };
  tickets.push(ticket);
  res.json({ success: true, data: { ticket }, message: '工单已创建' });
});

api.get('/tickets/:ticket', (req, res) => {
  const ticket = tickets.find(t => t.id === parseInt(req.params.ticket));
  if (!ticket) return res.status(404).json({ success: false, message: 'Ticket not found' });
  res.json({ success: true, data: ticket });
});

api.post('/tickets/:ticket/reply', (req, res) => {
  res.json({ success: true, message: '回复成功' });
});

api.post('/tickets/:ticket/close', (req, res) => {
  const ticket = tickets.find(t => t.id === parseInt(req.params.ticket));
  if (ticket) ticket.status = 'closed';
  res.json({ success: true, message: '工单已关闭' });
});

// Announcements
api.get('/announcements', (req, res) => {
  res.json({ success: true, data: { data: announcements } });
});

api.get('/announcements/:announcement', (req, res) => {
  const a = announcements.find(a => a.id === parseInt(req.params.announcement));
  if (!a) return res.status(404).json({ success: false, message: 'Not found' });
  res.json({ success: true, data: a });
});

// Verification
api.get('/verification', (req, res) => {
  res.json({ success: true, data: { status: 'approved', name: '张*三', id_card: '320***********1234', submitted_at: '2026-06-20T10:00:00Z' } });
});

api.post('/verification', (req, res) => {
  res.json({ success: true, message: '实名认证已提交，等待审核' });
});

api.put('/verification', (req, res) => {
  res.json({ success: true, message: '认证信息已更新' });
});

// Billing
api.get('/billing', (req, res) => {
  res.json({ success: true, data: {
    total_spent: 129.7,
    current_month: 39.9,
    balance: mockUser.balance,
    next_due: '2026-08-15',
    vms_count: vms.filter(v => v.user_id === req.user.id).length,
  }});
});

api.get('/billing/transactions', (req, res) => {
  res.json({ success: true, data: { data: [
    { id: 1, type: 'payment', amount: -39.9, description: '基础型 月付', created_at: '2026-06-15T08:05:00Z' },
    { id: 2, type: 'payment', amount: -79.9, description: '进阶型 月付', created_at: '2026-06-20T08:05:00Z' },
    { id: 3, type: 'recharge', amount: 200, description: '账户充值', created_at: '2026-06-10T12:00:00Z' },
  ] }});
});

api.get('/billing/expiring', (req, res) => {
  res.json({ success: true, data: { data: vms.filter(v => v.user_id === req.user.id).map(v => ({ id: v.id, name: v.name, expires_at: v.expires_at })) } });
});

// ======================================================
// ADMIN API ROUTES
// ======================================================
const adminApi = express.Router();
adminApi.use(adminAuth);

adminApi.get('/admin/dashboard', (req, res) => {
  res.json({ success: true, data: {
    total_users: 156, total_vms: 82, total_orders: 342, total_revenue: 15890.5,
    nodes_online: 2, nodes_total: 3,
    recent_orders: orders,
  }});
});

// Admin Users
adminApi.get('/admin/users', (req, res) => {
  res.json({ success: true, data: { data: [
    { id: 1, name: '测试用户', email: 'qdmz@vip.qq.com', balance: 520, verified: true, status: 'active', role: 'user', created_at: '2026-01-15T08:00:00Z' },
    { id: 2, name: 'demo', email: 'demo@example.com', balance: 120, verified: true, status: 'active', role: 'user', created_at: '2026-02-20T10:00:00Z' },
    { id: 3, name: 'suspended', email: 'bad@example.com', balance: 0, verified: false, status: 'suspended', role: 'user', created_at: '2026-03-01T08:00:00Z' },
  ], total: 156 }});
});

adminApi.get('/admin/users/:user', (req, res) => {
  res.json({ success: true, data: { id: req.params.user, name: '用户' + req.params.user, email: 'user' + req.params.user + '@example.com', balance: 100, verified: true, status: 'active' } });
});

adminApi.put('/admin/users/:user', (req, res) => {
  res.json({ success: true, message: '用户信息已更新' });
});

adminApi.post('/admin/users/:user/disable', (req, res) => {
  res.json({ success: true, message: '用户已禁用' });
});

adminApi.post('/admin/users/:user/enable', (req, res) => {
  res.json({ success: true, message: '用户已启用' });
});

adminApi.delete('/admin/users/:user', (req, res) => {
  res.json({ success: true, message: '用户已删除' });
});

// Admin VMs
adminApi.get('/admin/vms', (req, res) => {
  res.json({ success: true, data: { data: vms.map(v => ({ ...v, user: { id: 1, name: '测试用户', email: 'qdmz@vip.qq.com' }, node: nodes.find(n => n.id === v.node_id) })), total: 82 }});
});

adminApi.get('/admin/vms/:vm', (req, res) => {
  const vm = vms.find(v => v.id === parseInt(req.params.vm));
  if (!vm) return res.status(404).json({ success: false, message: 'VM not found' });
  res.json({ success: true, data: { ...vm, user: { id: 1, name: '测试用户' }, node: nodes[0] } });
});

adminApi.put('/admin/vms/:vm', (req, res) => {
  const vm = vms.find(v => v.id === parseInt(req.params.vm));
  if (vm) Object.assign(vm, req.body);
  res.json({ success: true, message: 'VM配置已更新' });
});

adminApi.post('/admin/vms/:vm/start', (req, res) => {
  const vm = vms.find(v => v.id === parseInt(req.params.vm));
  if (vm) vm.status = 'running';
  res.json({ success: true, message: '开机成功' });
});

adminApi.post('/admin/vms/:vm/stop', (req, res) => {
  const vm = vms.find(v => v.id === parseInt(req.params.vm));
  if (vm) vm.status = 'stopped';
  res.json({ success: true, message: '关机成功' });
});

adminApi.post('/admin/vms/:vm/restart', (req, res) => {
  res.json({ success: true, message: '重启成功' });
});

adminApi.post('/admin/vms/:vm/suspend', (req, res) => {
  const vm = vms.find(v => v.id === parseInt(req.params.vm));
  if (vm) vm.status = 'suspended';
  res.json({ success: true, message: 'VM已暂停' });
});

adminApi.post('/admin/vms/:vm/unsuspend', (req, res) => {
  const vm = vms.find(v => v.id === parseInt(req.params.vm));
  if (vm) vm.status = 'running';
  res.json({ success: true, message: 'VM已恢复' });
});

adminApi.delete('/admin/vms/:vm', (req, res) => {
  const idx = vms.findIndex(v => v.id === parseInt(req.params.vm));
  if (idx !== -1) vms.splice(idx, 1);
  res.json({ success: true, message: 'VM已删除' });
});

adminApi.post('/admin/vms/batch', (req, res) => {
  res.json({ success: true, message: '批量操作完成' });
});

// Admin Nodes
adminApi.get('/admin/nodes', (req, res) => {
  res.json({ success: true, data: { data: nodes } });
});

adminApi.get('/admin/nodes/:node', (req, res) => {
  const node = nodes.find(n => n.id === parseInt(req.params.node));
  if (!node) return res.status(404).json({ success: false, message: 'Node not found' });
  res.json({ success: true, data: node });
});

adminApi.post('/admin/nodes', (req, res) => {
  const node = { id: nodes.length + 1, ...req.body, status: 'online' };
  nodes.push(node);
  res.json({ success: true, data: { node }, message: '节点已添加' });
});

adminApi.put('/admin/nodes/:node', (req, res) => {
  res.json({ success: true, message: '节点已更新' });
});

adminApi.delete('/admin/nodes/:node', (req, res) => {
  res.json({ success: true, message: '节点已删除' });
});

adminApi.post('/admin/nodes/:node/test', (req, res) => {
  res.json({ success: true, message: '节点连接测试成功！PVE 8.4.19, 16核CPU, 31.3GB内存可用' });
});

adminApi.post('/admin/nodes/:node/sync-vms', (req, res) => {
  res.json({ success: true, message: '已同步 82 个虚拟机', data: { synced: 82 } });
});

adminApi.post('/admin/nodes/:node/sync-templates', (req, res) => {
  res.json({ success: true, message: '已同步 9 个模板', data: { synced: 9, templates: [
    { volid: 'local:iso/ubuntu-22.04.4-live-server-amd64.iso', name: 'Ubuntu 22.04' },
    { volid: 'local:iso/debian-12.5.0-amd64-netinst.iso', name: 'Debian 12' },
  ]} });
});

adminApi.put('/admin/nodes/:node/nat-config', (req, res) => {
  res.json({ success: true, message: 'NAT配置已更新' });
});

// Admin Products
adminApi.get('/admin/products', (req, res) => {
  res.json({ success: true, data: { data: products } });
});

adminApi.get('/admin/products/:product', (req, res) => {
  const p = products.find(p => p.id === parseInt(req.params.product));
  if (!p) return res.status(404).json({ success: false });
  res.json({ success: true, data: p });
});

adminApi.post('/admin/products', (req, res) => {
  const p = { id: products.length + 1, ...req.body, status: 'active' };
  products.push(p);
  res.json({ success: true, data: { product: p }, message: '产品已创建' });
});

adminApi.put('/admin/products/:product', (req, res) => {
  const p = products.find(p => p.id === parseInt(req.params.product));
  if (p) Object.assign(p, req.body);
  res.json({ success: true, message: '产品已更新' });
});

adminApi.delete('/admin/products/:product', (req, res) => {
  const idx = products.findIndex(p => p.id === parseInt(req.params.product));
  if (idx !== -1) products.splice(idx, 1);
  res.json({ success: true, message: '产品已删除' });
});

adminApi.put('/admin/products/:product/status', (req, res) => {
  const p = products.find(p => p.id === parseInt(req.params.product));
  if (p) p.status = p.status === 'active' ? 'inactive' : 'active';
  res.json({ success: true, message: '状态已切换' });
});

adminApi.put('/admin/products/sort', (req, res) => {
  res.json({ success: true, message: '排序已更新' });
});

// Admin Orders
adminApi.get('/admin/orders', (req, res) => {
  res.json({ success: true, data: { data: orders, total: 342 }});
});

adminApi.get('/admin/orders/:order', (req, res) => {
  const order = orders.find(o => o.id === parseInt(req.params.order));
  if (!order) return res.status(404).json({ success: false });
  res.json({ success: true, data: order });
});

adminApi.post('/admin/orders/:order/mark-paid', (req, res) => {
  const order = orders.find(o => o.id === parseInt(req.params.order));
  if (order) order.status = 'paid';
  res.json({ success: true, message: '已标记为已支付' });
});

adminApi.post('/admin/orders/:order/refund', (req, res) => {
  const order = orders.find(o => o.id === parseInt(req.params.order));
  if (order) order.status = 'refunded';
  res.json({ success: true, message: '已退款' });
});

adminApi.get('/admin/orders-stats', (req, res) => {
  res.json({ success: true, data: { today: 8, today_amount: 359.2, week: 45, week_amount: 2890, month: 186, month_amount: 12450 } });
});

// Admin Tickets
adminApi.get('/admin/tickets', (req, res) => {
  res.json({ success: true, data: { data: tickets } });
});

adminApi.get('/admin/tickets/:ticket', (req, res) => {
  const ticket = tickets.find(t => t.id === parseInt(req.params.ticket));
  if (!ticket) return res.status(404).json({ success: false });
  res.json({ success: true, data: ticket });
});

adminApi.post('/admin/tickets/:ticket/reply', (req, res) => {
  res.json({ success: true, message: '回复成功' });
});

adminApi.put('/admin/tickets/:ticket/status', (req, res) => {
  res.json({ success: true, message: '状态已更新' });
});

adminApi.put('/admin/tickets/:ticket/priority', (req, res) => {
  res.json({ success: true, message: '优先级已更新' });
});

// Admin Coupons
adminApi.get('/admin/coupons', (req, res) => {
  res.json({ success: true, data: { data: [
    { id: 1, code: 'SUMMER2026', type: 'percentage', value: 20, min_amount: 50, max_uses: 100, used: 23, status: 'active', expires_at: '2026-09-01' },
  ] } });
});

adminApi.post('/admin/coupons', (req, res) => {
  res.json({ success: true, message: '优惠券已创建' });
});

adminApi.put('/admin/coupons/:coupon', (req, res) => {
  res.json({ success: true, message: '优惠券已更新' });
});

adminApi.delete('/admin/coupons/:coupon', (req, res) => {
  res.json({ success: true, message: '优惠券已删除' });
});

// Admin Announcements
adminApi.get('/admin/announcements', (req, res) => {
  res.json({ success: true, data: { data: announcements } });
});

adminApi.post('/admin/announcements', (req, res) => {
  const a = { id: announcements.length + 1, ...req.body, created_at: now() };
  announcements.push(a);
  res.json({ success: true, data: { announcement: a }, message: '公告已创建' });
});

adminApi.put('/admin/announcements/:announcement', (req, res) => {
  res.json({ success: true, message: '公告已更新' });
});

adminApi.delete('/admin/announcements/:announcement', (req, res) => {
  res.json({ success: true, message: '公告已删除' });
});

adminApi.put('/admin/announcements/:announcement/pin', (req, res) => {
  const a = announcements.find(a => a.id === parseInt(req.params.announcement));
  if (a) a.pinned = !a.pinned;
  res.json({ success: true, message: '置顶状态已切换' });
});

adminApi.put('/admin/announcements/:announcement/status', (req, res) => {
  res.json({ success: true, message: '状态已更新' });
});

// Admin Verifications
adminApi.get('/admin/verifications', (req, res) => {
  res.json({ success: true, data: { data: [
    { id: 1, user_id: 1, user_name: '测试用户', id_card: '320***********1234', status: 'approved', submitted_at: '2026-06-20T10:00:00Z', reviewed_at: '2026-06-21T09:00:00Z' },
    { id: 2, user_id: 4, user_name: 'newuser', id_card: '110***********5678', status: 'pending', submitted_at: now() },
  ] } });
});

adminApi.get('/admin/verifications/:verification', (req, res) => {
  res.json({ success: true, data: { id: 1, user_id: 1, user_name: '测试用户', image_front: '...', image_back: '...', status: 'pending' } });
});

adminApi.post('/admin/verifications/:verification/approve', (req, res) => {
  res.json({ success: true, message: '已通过' });
});

adminApi.post('/admin/verifications/:verification/reject', (req, res) => {
  res.json({ success: true, message: '已驳回' });
});

// Admin Backups
adminApi.get('/admin/backups', (req, res) => {
  res.json({ success: true, data: { data: [
    { id: 1, vm_id: 100, vm_name: 'web-server-01', type: 'full', size: 2147483648, status: 'completed', created_at: '2026-06-30T02:00:00Z' },
  ] } });
});

adminApi.post('/admin/backups', (req, res) => {
  res.json({ success: true, message: '备份任务已创建' });
});

// Admin Settings
adminApi.get('/admin/settings/:group', (req, res) => {
  const settings = {
    general: { site_name: 'CloudVM Pro', site_description: '高性能 PVE 虚拟机平台', currency: 'CNY', language: 'zh-CN' },
    mail: { host: 'smtp.qq.com', port: 465, encryption: 'ssl', username: 'qdmz@vip.qq.com', from_address: 'qdmz@vip.qq.com', from_name: 'CloudVM Pro' },
    epay: { api_url: 'https://pay.wanjuanxueyi.com/submit.php', pid: '2093', sign_type: 'MD5' },
    security: { max_login_attempts: 5, session_lifetime: 120, require_verify: true, min_password_length: 8 },
  };
  res.json({ success: true, data: settings[req.params.group] || {} });
});

adminApi.put('/admin/settings/:group', (req, res) => {
  res.json({ success: true, message: '设置已保存' });
});

adminApi.post('/admin/settings/test-smtp', (req, res) => {
  res.json({ success: true, message: 'SMTP连接测试成功！邮件已发送到 qdmz@vip.qq.com' });
});

adminApi.post('/admin/settings/test-epay', (req, res) => {
  res.json({ success: true, message: '易支付连接测试成功！MD5签名验证通过' });
});


// ======================================================
// MOUNT ROUTES
// ======================================================
app.use('/api', api);
app.use('/api', adminApi);

// SPA fallback — serve index.html for all non-API, non-static routes
app.use((req, res) => {
  // If the request looks like a page route (no file extension), serve index.html
  if (!path.extname(req.path) && !req.path.startsWith('/api')) {
    res.sendFile(path.join(__dirname, 'public', 'index.html'));
  } else {
    res.status(404).send('Not Found');
  }
});

// ======================================================
// START SERVER
// ======================================================
app.listen(PORT, () => {
  console.log('');
  console.log('  ╔══════════════════════════════════════════╗');
  console.log('  ║   CloudVM Pro — Local Dev Server         ║');
  console.log(`  ║   Frontend: http://localhost:${PORT}          ║`);
  console.log(`  ║   API:      http://localhost:${PORT}/api     ║`);
  console.log('  ║                                          ║');
  console.log('  ║   页面导航:                               ║');
  console.log(`  ║   首页:   http://localhost:${PORT}            ║`);
  console.log(`  ║   登录:   http://localhost:${PORT}/views/auth/login.html   ║`);
  console.log(`  ║   用户面板: http://localhost:${PORT}/views/user/dashboard.html ║`);
  console.log(`  ║   管理面板: http://localhost:${PORT}/views/admin/index.html  ║`);
  console.log('  ╚══════════════════════════════════════════╝');
  console.log('');
});
