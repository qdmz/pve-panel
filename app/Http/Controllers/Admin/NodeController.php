<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreateNodeRequest;
use App\Models\Node;
use App\Models\VirtualMachine;
use App\Services\ProxmoxService;
use Illuminate\Http\Request;

class NodeController extends Controller
{
    public function index()
    {
        try {
            $nodes = Node::withCount('virtualMachines')
                       ->orderBy('created_at', 'desc')
                       ->get();

            return ApiResponse::success(['nodes' => $nodes]);
        } catch (\Exception $e) {
            \Log::error('Admin\\NodeController::index failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to retrieve nodes.', 500);
        }
    }

    public function show(Node $node)
    {
        try {
            $node->load('virtualMachines:id,name,status,vm_id,node_id,type');

            return ApiResponse::success(['node' => $node]);
        } catch (\Exception $e) {
            \Log::error('Admin\\NodeController::show failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to retrieve node.', 500);
        }
    }

    public function store(CreateNodeRequest $request)
    {
        try {
            $data = $request->validated();
            $data['port']           = $data['port'] ?? 8006;
            $data['virtualization'] = $data['virtualization'] ?? 'both';
            $data['bridge']         = $data['bridge'] ?? 'vmbr0';
            $data['storage']        = $data['storage'] ?? 'local-lvm';
            $data['status']         = 'offline'; // Will be updated after sync

            $node = Node::create($data);

            // Try to connect & sync with PVE API (non-blocking — failure is OK)
            try {
                $proxmox = new ProxmoxService();
                $result   = $proxmox->testConnection($node);

                if ($result['success']) {
                    // Pull resource stats and mark online
                    $resources = $proxmox->getNodeResources($node);
                    $node->update([
                        'cpu_total'    => $resources['cpu_total']    ?? 0,
                        'cpu_used'     => $resources['cpu_used']     ?? 0,
                        'memory_total' => $resources['memory_total'] ?? 0,
                        'memory_used'  => $resources['memory_used']  ?? 0,
                        'disk_total'   => $resources['disk_total']   ?? 0,
                        'disk_used'    => $resources['disk_used']    ?? 0,
                        'status'       => 'online',
                        'last_sync_at' => now(),
                    ]);
                }
            } catch (\Exception $e) {
                \Log::warning('NodeController::store PVE sync failed (non-fatal)', [
                    'node'  => $node->name,
                    'error' => $e->getMessage(),
                ]);
                // Node is saved; stats will sync later
            }

            $node->refresh();
            return ApiResponse::success(['node' => $node], 'Node added successfully.', 201);
        } catch (\Exception $e) {
            \Log::error('Admin\\NodeController::store failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to add node: ' . $e->getMessage(), 500);
        }
    }

    public function update(Request $request, Node $node)
    {
        try {
            $allowed = [
                'name', 'host', 'port', 'auth_type',
                'username', 'password', 'realm',
                'api_token', 'virtualization',
                'bridge', 'storage', 'nat_network',
                'ipv6_bridge', 'nat_enabled', 'notes',
            ];

            $data = $request->only($allowed);
            $node->update($data);

            return ApiResponse::success(['node' => $node], 'Node updated.');
        } catch (\Exception $e) {
            \Log::error('Admin\\NodeController::update failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to update node.', 500);
        }
    }

    public function destroy(Node $node)
    {
        try {
            if ($node->virtualMachines()->count() > 0) {
                return ApiResponse::error('Cannot delete node with active VMs.', 400);
            }

            $node->delete();

            return ApiResponse::success(null, 'Node deleted.');
        } catch (\Exception $e) {
            \Log::error('Admin\\NodeController::destroy failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to delete node.', 500);
        }
    }

    /**
     * Test connectivity to a PVE node.
     */
    public function test(Node $node)
    {
        try {
            $proxmox = new ProxmoxService();
            $result  = $proxmox->testConnection($node);

            if ($result['success']) {
                // Also update node status to online
                $node->update(['status' => 'online', 'last_sync_at' => now()]);
                return ApiResponse::success($result, 'Connection successful.');
            }

            return ApiResponse::error('Connection failed: ' . ($result['message'] ?? 'Unknown error'), 400);
        } catch (\Exception $e) {
            \Log::error('Admin\\NodeController::test failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Connection test failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Test connectivity to a new PVE node (before creation).
     * Used by the frontend modal's "Test Connection" button.
     */
    public function testConnection(Request $request)
    {
        try {
            $host = $request->input('host');
            $port = $request->input('port', 8006);

            if (!$host) {
                return ApiResponse::error('Host address is required.', 422);
            }

            $proxmox = new ProxmoxService();
            // Create a temporary node model for testing
            $tempNode = new Node([
                'host' => $host,
                'port' => $port,
                'auth_type' => 'api_token',
            ]);
            $result = $proxmox->testConnection($tempNode);

            if ($result['success']) {
                return ApiResponse::success($result, 'Connection successful.');
            }

            return ApiResponse::error('Connection failed: ' . ($result['message'] ?? 'Unknown error'), 400);
        } catch (\Exception $e) {
            \Log::error('Admin\\NodeController::testConnection failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Connection test failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Sync VMs from PVE to local DB.
     */
    public function syncVms(Node $node)
    {
        try {
            $proxmox = new ProxmoxService();
            $result  = $proxmox->syncNodeVms($node);

            if ($result['success']) {
                return ApiResponse::success([
                    'total_pve_vms' => $result['total_pve_vms'] ?? 0,
                    'synced'        => $result['synced']        ?? 0,
                    'resources'     => $result['resources']     ?? [],
                ], 'VMs synced successfully.');
            }

            return ApiResponse::error('Sync failed: ' . ($result['error'] ?? 'Unknown error'), 502);
        } catch (\Exception $e) {
            \Log::error('Admin\\NodeController::syncVms failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to sync VMs.', 500);
        }
    }

    /**
     * Sync OS templates from PVE node storage.
     */
    public function syncTemplates(Node $node)
    {
        try {
            $proxmox = new ProxmoxService();
            $result  = $proxmox->syncNodeTemplates($node);

            if ($result['success']) {
                return ApiResponse::success(['templates' => $result['templates'] ?? []], 'Templates synced.');
            }

            return ApiResponse::error('Template sync failed: ' . ($result['error'] ?? 'Unknown error'), 502);
        } catch (\Exception $e) {
            \Log::error('Admin\\NodeController::syncTemplates failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to sync templates.', 500);
        }
    }

    /**
     * Update NAT configuration for a node.
     */
    public function updateNatConfig(Request $request, Node $node)
    {
        try {
            $node->update($request->only([
                'nat_enabled', 'nat_network', 'public_ip',
                'port_range_start', 'port_range_end', 'ipv6_bridge',
            ]));

            return ApiResponse::success(['node' => $node], 'NAT config updated.');
        } catch (\Exception $e) {
            \Log::error('Admin\\NodeController::updateNatConfig failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to update NAT config.', 500);
        }
    }

    /**
     * Get current resource stats for a node.
     */
    public function resources(Node $node)
    {
        try {
            $proxmox   = new ProxmoxService();
            $resources = $proxmox->getNodeResources($node);

            // Update local DB
            $node->update([
                'cpu_total'    => $resources['cpu_total']    ?? $node->cpu_total,
                'cpu_used'     => $resources['cpu_used']     ?? $node->cpu_used,
                'memory_total' => $resources['memory_total'] ?? $node->memory_total,
                'memory_used'  => $resources['memory_used']  ?? $node->memory_used,
                'disk_total'   => $resources['disk_total']   ?? $node->disk_total,
                'disk_used'    => $resources['disk_used']    ?? $node->disk_used,
                'last_sync_at' => now(),
            ]);

            return ApiResponse::success(['resources' => $resources]);
        } catch (\Exception $e) {
            \Log::error('Admin\\NodeController::resources failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to get node resources.', 500);
        }
    }
}
