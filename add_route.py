# -*- coding: utf-8 -*-
"""Add template route to admin routes - no escape issues"""

route_file = r'/var/www/pve-panel/routes/admin.php'

with open(route_file, 'r', encoding='utf-8') as f:
    content = f.read()

# 1. Add import if not present
search_import = 'use App' + chr(92) + 'Http' + chr(92) + 'Controllers' + chr(92) + 'Admin' + chr(92) + 'NodeController;'
new_import = 'use App' + chr(92) + 'Http' + chr(92) + 'Controllers' + chr(92) + 'Admin' + chr(92) + 'NodeTemplateController;'
if new_import not in content:
    content = content.replace(search_import, search_import + chr(10) + new_import)
    print('Added import')

# 2. Add route after sync-templates
search_route = "Route::post('nodes/{node}/sync-templates', [NodeController::class, 'syncTemplates']);"
new_route = "    Route::get('nodes/{node}/templates', [NodeTemplateController::class, 'index']);"
if search_route in content and new_route not in content:
    content = content.replace(search_route, search_route + chr(10) + new_route)
    print('Added route')
else:
    print('Route already exists or search not found')

with open(route_file, 'w', encoding='utf-8') as f:
    f.write(content)
print('Done')
