<?php

namespace App\Console\Commands;

use App\Models\Node;
use App\Services\ProxmoxService;
use Illuminate\Console\Command;

class SyncNodeData extends Command
{
    protected $signature = 'nodes:sync {--node= : Sync a specific node by ID}';
    protected $description = 'Sync all node data (VMs, templates, resources)';

    public function handle(): int
    {
        $nodeId = $this->option('node');

        if ($nodeId) {
            $nodes = Node::where('id', $nodeId)->get();
        } else {
            $nodes = Node::all();
        }

        if ($nodes->isEmpty()) {
            $this->warn('No nodes found to sync.');
            return Command::SUCCESS;
        }

        $this->info("Syncing {$nodes->count()} node(s)...");

        foreach ($nodes as $node) {
            $this->syncNode($node);
        }

        $this->info('Node sync completed.');

        return Command::SUCCESS;
    }

    protected function syncNode(Node $node): void
    {
        $this->info("Syncing node: {$node->name} ({$node->host})");

        if (!$node->isOnline()) {
            $this->warn("  Node is marked offline, attempting connection...");
        }

        $proxmox = new ProxmoxService($node);

        $testResult = $proxmox->testConnection();

        if (!$testResult['success']) {
            $this->error("  Cannot connect to node: {$testResult['message']}");
            $node->update(['status' => 'offline']);
            return;
        }

        if (!$proxmox->authenticate()) {
            $this->error('  Failed to authenticate');
            $node->update(['status' => 'offline']);
            return;
        }

        // Sync resources
        $resources = $proxmox->getResources();

        if ($resources) {
            $nodeInfo = collect($resources)->first(function ($r) use ($node) {
                return ($r['type'] ?? '') === 'node' && ($r['node'] ?? '') === $node->name;
            });

            if ($nodeInfo) {
                $node->update([
                    'status'       => 'online',
                    'max_cpu'      => $nodeInfo['maxcpu'] ?? $node->max_cpu,
                    'max_memory'   => $nodeInfo['maxmem'] ?? $node->max_memory,
                    'max_disk'     => $nodeInfo['maxdisk'] ?? $node->max_disk,
                    'cpu_usage'    => $nodeInfo['cpu'] ?? 0,
                    'memory_usage' => $nodeInfo['mem'] ?? 0,
                    'disk_usage'   => $nodeInfo['disk'] ?? 0,
                ]);

                $this->line("  CPU: {$nodeInfo['cpu']}%, Memory: {$nodeInfo['mem']}%, Disk: {$nodeInfo['disk']}%");
            }
        }

        $node->update(['status' => 'online']);

        // Sync VMs
        $vms = $proxmox->getVms($node->name);
        $this->line("  Found " . count($vms) . " VMs (QEMU)");

        $containers = $proxmox->getContainers($node->name);
        $this->line("  Found " . count($containers) . " Containers (LXC)");

        // Sync templates
        $templates = $proxmox->getTemplates($node->name);
        $this->line("  Found templates: " . (is_array($templates) ? count($templates) : 0));

        $this->info("  Node {$node->name} synced successfully.");
    }
}
