#!/usr/bin/env python3
import os

pages = {}
for root, dirs, files in os.walk('public'):
    for f in files:
        if f.endswith('.html') and '.bak' not in f:
            rel = os.path.relpath(os.path.join(root, f), 'public')
            pages[rel] = os.path.join(root, f)

for rel in sorted(pages):
    fp = pages[rel]
    with open(fp, 'r', errors='ignore') as f:
        c = f.read()
    
    issues = []
    
    # 1. Simulate API calls
    if 'Simulate' in c or 'simulate' in c:
        issues.append('模拟API调用')
    
    # 2. Math.random() chart data
    if 'Math.random()' in c:
        issues.append('随机图表数据')
    
    # 3. Hardcoded VM list  
    if 'vm-card' in c and 'fetch(' not in c and 'api.js' not in c:
        issues.append('硬编码VM列表')
    
    # 4. Hardcoded user data
    if '张三' in c or '¥128.50' in c:
        issues.append('硬编码用户数据')
    
    # 5. No API calls at all
    has_api = 'fetch(' in c or 'axios.' in c or 'api.js' in c
    if not has_api:
        issues.append('无API调用')
    
    if issues:
        print(f'{rel}: {", ".join(issues)}')
    else:
        print(f'{rel}: ✅ 正常')
