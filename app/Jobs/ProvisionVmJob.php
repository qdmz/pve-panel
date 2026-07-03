<?php

namespace App\Jobs;

use App\Models\VirtualMachine;
use App\Services\ProxmoxService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProvisionVmJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 600;

    public function __construct(
        protected VirtualMachine $vm,
    ) {}

    public function handle(ProxmoxService $proxmoxService): void
    {
        try {
            $this->vm->refresh();

            if ($this->vm->status !== 'creating') {
                \Log::warning('ProvisionVmJob: VM is not in creating status', [
                    'vm_id'  => $this->vm->id,
                    'status' => $this->vm->status,
                ]);
                return;
            }

            $node = $this->vm->node;

            if (!$node || !$node->isOnline()) {
                \Log::error('ProvisionVmJob: Node is offline or not found', [
                    'vm_id'   => $this->vm->id,
                    'node_id' => $this->vm->node_id,
                ]);
                $this->vm->update(['status' => 'error']);
                return;
            }

            // Test connection first
            $testResult = $proxmoxService->testConnection($node);
            if (!$testResult['success']) {
                \Log::error('ProvisionVmJob: Proxmox connection failed', [
                    'vm_id'  => $this->vm->id,
                    'node'   => $node->name,
                    'error'  => $testResult['message'],
                ]);
                $this->vm->update(['status' => 'error']);
                return;
            }

            // Create the VM with dual NIC (NAT IPv4 + IPv6)
            $proxmoxService->createVm($this->vm);

            // Refresh to get the assigned nat_ipv4
            $this->vm->refresh();

            // Start the VM
            if ($this->vm->ip || $this->vm->nat_ipv4) {
                try {
                    $proxmoxService->startVm($this->vm);
                } catch (\Exception $e) {
                    \Log::warning("VM created but start failed: " . $e->getMessage());
                }
            }

            // Mark as running
            $this->vm->update(['status' => 'running']);

            \Log::info("ProvisionVmJob completed successfully", [
                'vm_id'     => $this->vm->id,
                'vmid'      => $this->vm->vm_id,
                'nat_ipv4'  => $this->vm->nat_ipv4,
                'ipv6'      => $this->vm->ipv6_address,
            ]);

        } catch (\Exception $e) {
            \Log::error('ProvisionVmJob failed', [
                'vm_id'  => $this->vm->id,
                'error'  => $e->getMessage(),
                'trace'  => $e->getTraceAsString(),
            ]);
            $this->vm->update(['status' => 'error']);
        }
    }
}
