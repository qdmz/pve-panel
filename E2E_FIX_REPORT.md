# PVE Panel E2E 测试 & Bug 修复报告

**日期**: 2026-07-03  
**环境**: pve.ypvps.com (38.76.178.60)

---

## 🔧 修复的 Bug

### 1. PaymentController::notify() — VM 开通失败（核心 Bug）
- **问题**: `dispatch(new ProvisionVmJob($order))` 传入了 `Order` 对象，但 `ProvisionVmJob` 构造函数期望 `VirtualMachine`
- **修复**: 提取 `VmProvisioningService` 共享类，在回调中先创建 VM 记录再 dispatch Job
- **文件**: `app/Services/VmProvisioningService.php` (新建), `PaymentController.php`, `OrderController.php`

### 2. Transaction 表缺少 transaction_id 字段
- **问题**: `PaymentController::notify()` 中 `Transaction::create()` 尝试写入 `transaction_id` 但列不存在
- **修复**: 创建 migration `2026_07_03_165356_add_transaction_id_to_transactions_table.php`

### 3. Order 模型 payment_status vs status 不一致
- **问题**: `OrderController::store()` 使用 `status` 字段而模型定义的是 `payment_status`
- **修复**: 改为 `payment_status`

### 4. VmProvisioningService 列名错误
- **问题**: `VirtualMachine::max('vmid')` 但数据库列名是 `vm_id`
- **修复**: 改为 `max('vm_id')`

### 5. VM 名称 DNS 格式兼容
- **问题**: 中文产品名（如 `入门型 VPS-7471`）导致 PVE API 返回 "invalid DNS name"
- **修复**: 添加名称清理逻辑，替换非 ASCII 字符为 `-`

---

## ✅ E2E 测试结果

| 步骤 | 状态 | 详情 |
|------|------|------|
| CSRF Cookie | ✅ | `/sanctum/csrf-cookie` 返回 204 |
| 用户注册 | ✅ | 创建用户 ID 12 |
| 用户登录 | ✅ | 返回 Bearer token |
| 产品列表 | ✅ | 5 个产品正常返回 |
| 订单创建 | ✅ | ORD-202607031701372935, ¥19.90 |
| Epay 回调签名验证 | ✅ | MD5 签名验证通过 |
| 订单状态更新 | ✅ | payment_status: pending → paid |
| Transaction 记录 | ✅ | 含 transaction_id 字段 |
| VM 记录创建 | ✅ | VM#3: VPS-6214, status=creating |
| ProvisionVmJob 分发 | ✅ | Job 已入队并执行 |
| PVE API 调用 | ⚠️ | 存储 `local-lvm` 不存在（基础设施） |

**数据库最终状态**:
```
Orders: #8 paid + vm_id=3 + transaction_id
VM:    VM#3 VPS-6214 (node=1, 1C/1024M/20G)
Txn:   Txn#3 payment -19.90, ref=order#8
```

---

## 📁 修改文件清单

| 文件 | 操作 |
|------|------|
| `app/Services/VmProvisioningService.php` | 新建 |
| `app/Http/Controllers/Api/PaymentController.php` | 修改 |
| `app/Http/Controllers/Api/OrderController.php` | 修改 |
| `app/Models/Order.php` | 修改 |
| `database/migrations/2026_07_03_165356_*.php` | 新建 |

## ⚠️ 待处理（非代码 Bug）

- PVE 节点 `pve-main` 缺少 `local-lvm` 存储池，需在 Proxmox 上配置或修改 `ProxmoxService` 中的存储名称
- 队列 Worker 未持久化运行（建议配置 Supervisor）
- 需手动 `git commit`（沙箱阻止了 git 操作）
