#!/usr/bin/env python3
"""Complete backend architecture changes for PVE Panel"""

import subprocess, sys

def run_ssh(cmd):
    """Run command on remote server via SSH"""
    result = subprocess.run([
        'ssh', '-o', 'StrictHostKeyChecking=no',
        'root@pve.ypvps.com', cmd
    ], capture_output=True, text=True, timeout=30)
    return result.stdout, result.stderr

print("=== 1. Adding max_ports_per_vm to nodes table ===")
stdout, stderr = run_ssh(
    'mysql -u pve_panel -ppanel123 pve_panel -e '
    '"ALTER TABLE nodes ADD COLUMN max_ports_per_vm INT DEFAULT 5 AFTER nat_network" 2>&1'
)
print(f"ALTER nodes: {stdout} {stderr}")

print("\n=== 2. Creating node_templates table ===")
stdout, stderr = run_ssh('''mysql -u pve_panel -ppanel123 pve_panel -e "
CREATE TABLE IF NOT EXISTS node_templates (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    node_id BIGINT UNSIGNED NOT NULL,
    template_id VARCHAR(100) NOT NULL,
    name VARCHAR(255) NOT NULL,
    type ENUM('kvm','lxc') NOT NULL DEFAULT 'kvm',
    format VARCHAR(50) DEFAULT NULL,
    size BIGINT UNSIGNED DEFAULT 0,
    description TEXT DEFAULT NULL,
    metadata JSON DEFAULT NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    INDEX node_templates_node_id_index (node_id),
    UNIQUE KEY node_templates_node_template_unique (node_id, template_id),
    FOREIGN KEY (node_id) REFERENCES nodes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
" 2>&1''')
print(f"CREATE node_templates: {stdout} {stderr}")

print("\n=== 3. Creating NodeTemplate model ===")
model_code = """<?php

namespace App\\Models;

use Illuminate\\Database\\Eloquent\\Model;
use Illuminate\\Database\\Eloquent\\Relations\\BelongsTo;

class NodeTemplate extends Model
{
    protected $fillable = [
        'node_id',
        'template_id',
        'name',
        'type',
        'format',
        'size',
        'description',
        'metadata',
    ];

    protected $casts = [
        'size' => 'integer',
        'metadata' => 'array',
    ];

    public function node(): BelongsTo
    {
        return $this->belongsTo(Node::class);
    }

    public function getLabel(): string
    {
        $typeLabel = $this->type === 'lxc' ? '[LXC]' : '[KVM]';
        return "{$typeLabel} {$this->name}";
    }
}
"""

# Write model via SFTP
print("Writing NodeTemplate model...")
stdout, stderr = run_ssh(f"cat > /var/www/pve-panel/app/Models/NodeTemplate.php << 'MODEL_EOF'\n{model_code}\nMODEL_EOF")
print(f"Model: {stdout} {stderr}")

print("\n=== 4. Updating Node model to add templates relationship ===")
patch = f"""sed -i '/public function natRules/a\\\\
    public function templates(): HasMany\\\\\
    {{\\\\
        return \\$this->hasMany(NodeTemplate::class);\\\\\
    }}\\\\
' /var/www/pve-panel/app/Models/Node.php && echo 'Patched'"""

stdout, stderr = run_ssh(patch)
print(f"Node model patch: {stdout} {stderr}")

# Add max_ports_per_vm to fillable and casts
stdout, stderr = run_ssh(
    "sed -i \"/'nat_network',/a\\\\        'max_ports_per_vm',\" /var/www/pve-panel/app/Models/Node.php && "
    "sed -i \"/'nat_end_port' => 'integer',/a\\\\        'max_ports_per_vm' => 'integer',\" /var/www/pve-panel/app/Models/Node.php && "
    "echo 'Node model fillable+casts updated'"
)
print(f"Node model update: {stdout} {stderr}")

print("\n=== 5. Creating NodeTemplateController ===")
controller_code = """<?php

namespace App\\Http\\Controllers\\Admin;

use App\\Helpers\\ApiResponse;
use App\\Http\\Controllers\\Controller;
use App\\Models\\Node;
use App\\Models\\NodeTemplate;

class NodeTemplateController extends Controller
{
    /**
     * Get templates for a node (from local DB cache).
     */
    public function index(Node \\$node)
    {
        try {
            \\$templates = \\$node->templates()
                ->orderBy('type')
                ->orderBy('name')
                ->get();

            return ApiResponse::success(['templates' => \\$templates]);
        } catch (\\Exception \\$e) {
            \\Log::error('NodeTemplateController::index failed', ['error' => \\$e->getMessage()]);
            return ApiResponse::error('Failed to load templates.', 500);
        }
    }
}
"""

stdout, stderr = run_ssh(f"cat > /var/www/pve-panel/app/Http/Controllers/Admin/NodeTemplateController.php << 'CTRL_EOF'\n{controller_code}\nCTRL_EOF")
print(f"Controller: {stdout} {stderr}")

print("\n=== 6. Updating NodeController syncTemplates to save to DB ===")
# Read current NodeController and update the syncTemplates method
stdout, stderr = run_ssh("sed -n '172,190p' /var/www/pve-panel/app/Http/Controllers/Admin/NodeController.php")
print(f"Current syncTemplates:\n{stdout}")

# Replace syncTemplates method
new_sync_method = r'''    /**
     * Sync OS templates from PVE node storage.
     */
    public function syncTemplates(Node $node)
    {
        try {
            $proxmox = new \App\Services\ProxmoxService();
            $result  = $proxmox->syncNodeTemplates($node);

            if ($result['success']) {
                // Save synced templates to local DB
                $templates = $result['templates'] ?? [];
                $savedCount = 0;

                foreach (['kvm', 'lxc'] as $type) {
                    foreach ($templates[$type] ?? [] as $tmpl) {
                        \App\Models\NodeTemplate::updateOrCreate(
                            [
                                'node_id' => $node->id,
                                'template_id' => $tmpl['volid'] ?? $tmpl['name'] ?? 'unknown',
                            ],
                            [
                                'name' => $tmpl['name'] ?? 'Unknown',
                                'type' => $type,
                                'format' => $tmpl['format'] ?? null,
                                'size' => $tmpl['size'] ?? 0,
                                'description' => $tmpl['description'] ?? null,
                                'metadata' => $tmpl,
                            ]
                        );
                        $savedCount++;
                    }
                }

                return ApiResponse::success([
                    'templates' => $templates,
                    'saved_count' => $savedCount,
                ], 'Templates synced and saved.');
            }

            return ApiResponse::error($result['message'] ?? 'Sync failed.', 500);
        } catch (\Exception $e) {
            \Log::error('Admin\\NodeController::syncTemplates failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to sync templates.', 500);
        }
    }'''

# Use python to do the replacement properly
fix_script = '''
import re, sys

with open("/var/www/pve-panel/app/Http/Controllers/Admin/NodeController.php", "r") as f:
    content = f.read()

# Find syncTemplates method and replace it
old_start = content.find("    public function syncTemplates(Node $node)")
if old_start == -1:
    print("syncTemplates not found!")
    sys.exit(1)
    
old_end = content.find("    }", old_start + 500)  # rough end
if old_end == -1:
    print("Could not find end of syncTemplates")
    sys.exit(1)

new_method = """    /**
     * Sync OS templates from PVE node storage.
     */
    public function syncTemplates(Node $node)
    {
        try {
            $proxmox = new \\\\App\\\\Services\\\\ProxmoxService();
            $result  = $proxmox->syncNodeTemplates($node);

            if ($result['success']) {
                $templates = $result['templates'] ?? [];
                $savedCount = 0;

                foreach (['kvm', 'lxc'] as $type) {
                    foreach ($templates[$type] ?? [] as $tmpl) {
                        \\\\App\\\\Models\\\\NodeTemplate::updateOrCreate(
                            [
                                'node_id' => $node->id,
                                'template_id' => $tmpl['volid'] ?? $tmpl['name'] ?? 'unknown',
                            ],
                            [
                                'name' => $tmpl['name'] ?? 'Unknown',
                                'type' => $type,
                                'format' => $tmpl['format'] ?? null,
                                'size' => $tmpl['size'] ?? 0,
                                'description' => $tmpl['description'] ?? null,
                                'metadata' => $tmpl,
                            ]
                        );
                        $savedCount++;
                    }
                }

                return ApiResponse::success([
                    'templates' => $templates,
                    'saved_count' => $savedCount,
                ], 'Templates synced and saved.');
            }

            return ApiResponse::error($result['message'] ?? 'Sync failed.', 500);
        } catch (\\\\Exception $e) {
            \\\\Log::error('Admin\\\\\\\\NodeController::syncTemplates failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to sync templates.', 500);
        }
    }
"""

content = content[:old_start] + new_method + content[old_end + 5:]

with open("/var/www/pve-panel/app/Http/Controllers/Admin/NodeController.php", "w") as f:
    f.write(content)

print("syncTemplates method updated!")
'''

with open('/tmp/fix_sync_templates.py', 'w') as f:
    f.write(fix_script)

stdout, stderr = run_ssh('python3 /tmp/fix_sync_templates.py 2>&1')
print(f"syncTemplates fix: {stdout} {stderr}")

print("\n=== 7. Adding API routes for node templates ===")
# Add route
stdout, stderr = run_ssh(r"""
grep -n "nodes.*templates" /var/www/pve-panel/routes/api.php 2>&1 || echo "Not found"
""")
print(f"Current routes: {stdout}")

# Add route for listing templates
route_patch = r"""sed -i "/Route::post('nodes\/{node}\/sync-templates'/a\        Route::get('nodes/{node}/templates', [App\\Http\\Controllers\\Admin\\NodeTemplateController::class, 'index']);" /var/www/pve-panel/routes/api.php && echo 'Route added'"""
stdout, stderr = run_ssh(route_patch)
print(f"Route patch: {stdout} {stderr}")

print("\n=== 8. Updating NodeController store/update to include max_ports_per_vm ===")
# Check what the store method looks like
stdout, stderr = run_ssh("sed -n '42,95p' /var/www/pve-panel/app/Http/Controllers/Admin/NodeController.php")
print(f"store method:\n{stdout}")

print("\nDone with backend changes!")
