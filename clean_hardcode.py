#!/usr/bin/env python3
import os

base = 'public/views/user'
replacements = {
    'billing.html': [
        ('张三</div><div class="user-balance">余额: ¥128.50</div></div>',
         '加载中...</div><div class="user-balance">余额: --</div></div>'),
        ('<div style="font-size:3rem;font-weight:900;color:var(--green)">¥128.50</div>',
         '<div style="font-size:3rem;font-weight:900;color:var(--green)" id="balanceVal">¥0.00</div>'),
        ('<tr><td>2025-07-01 10:23</td><td><span class="badge badge-success">充值</span></td><td>支付宝充值</td><td style="color:var(--green);font-weight:700">+¥100.00</td><td>¥128.50</td><td><span class="badge badge-success">成功</span></td></tr>',
         '<tr><td colspan="6" style="text-align:center;color:var(--text-3);padding:20px" id="billLoading">加载账单中...</td></tr>'),
    ],
    'dashboard.html': [
        ('<div class="user-name">张三</div>',
         '<div class="user-name">加载中...</div>'),
        ('<div class="user-balance">余额: ¥128.50</div>',
         '<div class="user-balance">余额: --</div>'),
        ('<p>欢迎回来，张三。您当前有 3 台虚拟机实例。</p>',
         '<p id="welcomeText">欢迎回来...</p>'),
        ('<div class="stat-card-value" style="color:var(--green)">¥128.50</div>',
         '<div class="stat-card-value" style="color:var(--green)" id="balanceDisplay">¥0.00</div>'),
    ],
    'instance-detail.html': [
        ('张三</div><div class="user-balance">余额: ¥128.50</div>',
         '加载中...</div><div class="user-balance">余额: --</div>'),
    ],
    'profile.html': [
        ('<input type="text" class="form-input" value="张三" placeholder="请输入真实姓名">',
         '<input type="text" class="form-input" id="profileName" placeholder="请输入真实姓名">'),
    ],
    'tickets.html': [
        ('张三</div><div class="user-balance">余额: ¥128.50</div>',
         '加载中...</div><div class="user-balance">余额: --</div>'),
        ('<span class="msg-sender">张三（用户）</span>',
         '<span class="msg-sender" id="msgSender">用户</span>'),
    ],
    'verify.html': [
        ('<div>认证姓名：张三</div>',
         '<div>认证姓名：<span id="verifyName">--</span></div>'),
    ],
}

for fname, reps in replacements.items():
    fpath = os.path.join(base, fname)
    if not os.path.exists(fpath):
        print(f"SKIP {fname}")
        continue
    with open(fpath, 'r', encoding='utf-8') as f:
        content = f.read()
    changed = False
    for old, new in reps:
        if old in content:
            content = content.replace(old, new)
            print(f"  FIXED {fname}: {old[:40]}...")
            changed = True
        else:
            print(f"  MISS {fname}: {old[:40]}...")
    if changed:
        with open(fpath, 'w', encoding='utf-8') as f:
            f.write(content)

print("\nDone!")
