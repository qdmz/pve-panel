<?php

namespace App\Services;

use App\Jobs\ProvisionVmJob;
use App\Models\Node;
use App\Models\Order;
use App\Models\VirtualMachine;
use Illuminate\Support\Facades\Log;

class VmProvisioningService
{
    /**
     * Create a VirtualMachine record from an Order and dispatch the provisioning job.
     */
    public function provisionFromOrder(Order $order): ?VirtualMachine
    {
        $product = $order->product;

        if (!$product) {
            Log::error('VmProvisioningService: Order has no product', [
                'order_id' => $order->id,
                'order_no' => $order->order_no,
            ]);
            return null;
        }

        $availableNode = Node::where('status', 'online')->first();

        if (!$availableNode) {
            Log::error('VmProvisioningService: No online nodes available', [
                'order_no' => $order->order_no,
            ]);
            return null;
        }

        $maxVmid = VirtualMachine::withTrashed()->max('vm_id');
        $nextVmid = $maxVmid ? (int) $maxVmid + 1 : 100;

        $expiresAt = match ($order->billing_cycle) {
            'quarterly' => now()->addMonths(3),
            'yearly'    => now()->addYear(),
            default     => now()->addMonth(),
        };

        // Sanitize VM name for Proxmox (DNS-safe: alphanumeric + hyphens only)
        $rawName = $product->name ?? 'VM';
        $safeName = preg_replace('/[^a-zA-Z0-9-]/', '-', $rawName);
        $safeName = trim($safeName, '-');
        $safeName = $safeName ?: 'vm';

        $vm = VirtualMachine::create([
            'user_id'       => $order->user_id,
            'node_id'       => $availableNode->id,
            'product_id'    => $product->id,
            'order_id'      => $order->id,
            'name'          => $safeName . '-' . rand(1000, 9999),
            'type'          => $product->type ?? 'kvm',
            'status'        => 'creating',
            'vm_id'         => (string) $nextVmid,
            'cpu'           => $product->cpu ?? 1,
            'memory'        => $product->memory ?? 512,
            'disk'          => $product->disk ?? 10,
            'bandwidth'     => $product->bandwidth ?? 100,
            'traffic_limit' => $product->traffic_limit ?? 0,
            'traffic_used'  => 0,
            'os_template'   => 'auto',
            'expires_at'    => $expiresAt,
        ]);

        $order->update(['vm_id' => $vm->id]);

        Log::info('VmProvisioningService: Dispatching ProvisionVmJob', [
            'order_no'  => $order->order_no,
            'vm_id'     => $vm->id,
            'vmid'      => $vm->vm_id,
            'product'   => $product->name,
        ]);

        ProvisionVmJob::dispatch($vm);

        return $vm;
    }
}
