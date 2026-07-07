# -*- coding: utf-8 -*-
"""Update NodeController - no escape issues"""

from pathlib import Path
controller_file = '/var/www/pve-panel/app/Http/Controllers/Admin/NodeController.php'

content = Path(controller_file).read_text(encoding='utf-8')

# 1. Update $allowed array
old = "                'bridge', 'storage', 'nat_network',\n                'ipv6_bridge', 'nat_enabled', 'notes',"
new = "                'bridge', 'storage', 'nat_network', 'nat_start_port', 'nat_end_port',\n                'ipv6_bridge', 'nat_enabled', 'max_ports_per_vm', 'notes',"
if old in content:
    content = content.replace(old, new)
    print('Updated allowed fields')

# 2. Update syncTemplates
old2 = "            if ($result['success']) {\n                return ApiResponse::success(['templates' => $result['templates'] ?? []], 'Templates synced.');"
new2 = """            if ($result['success']) {
                $templates = $result['templates'] ?? [];
                $savedCount = 0;
                foreach (['kvm', 'lxc'] as $type) {
                    foreach ($templates[$type] ?? [] as $tmpl) {
                        \\App\\Models\\NodeTemplate::updateOrCreate(
                            ['node_id' => $node->id, 'template_id' => $tmpl['volid'] ?? $tmpl['name'] ?? 'unknown'],
                            ['name' => $tmpl['name'] ?? 'Unknown', 'type' => $type,
                             'format' => $tmpl['format'] ?? null, 'size' => $tmpl['size'] ?? 0,
                             'description' => $tmpl['description'] ?? null, 'metadata' => $tmpl]
                        );
                        $savedCount++;
                    }
                }
                return ApiResponse::success([
                    'templates' => $templates,
                    'saved_count' => $savedCount,
                ], 'Templates synced and saved.');"""

if old2 in content:
    content = content.replace(old2, new2)
    print('Updated syncTemplates')

Path(controller_file).write_text(content, encoding='utf-8')
print('Done')
