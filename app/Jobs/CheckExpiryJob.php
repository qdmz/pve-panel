<?php

namespace App\Jobs;

use App\Models\VirtualMachine;
use App\Services\ProxmoxService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CheckExpiryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        // Notify VMs expiring in 7 days
        $expiring7Days = VirtualMachine::expiring(7)
            ->where('expires_at', '>', now()->addDays(3))
            ->get();

        foreach ($expiring7Days as $vm) {
            $this->notifyUser($vm, 7);
        }

        // Notify VMs expiring in 3 days
        $expiring3Days = VirtualMachine::expiring(3)
            ->where('expires_at', '>', now()->addDay())
            ->get();

        foreach ($expiring3Days as $vm) {
            $this->notifyUser($vm, 3);
        }

        // Notify VMs expiring in 1 day
        $expiring1Day = VirtualMachine::expiring(1)
            ->where('expires_at', '>', now())
            ->get();

        foreach ($expiring1Day as $vm) {
            $this->notifyUser($vm, 1);
        }

        // Auto-suspend VMs past expiry
        $expiredVms = VirtualMachine::where('expires_at', '<', now())
            ->whereNotIn('status', ['suspended', 'deleting', 'creating'])
            ->get();

        foreach ($expiredVms as $vm) {
            $this->suspendVm($vm);
        }

        \Log::info('CheckExpiryJob completed', [
            '7days'  => $expiring7Days->count(),
            '3days'  => $expiring3Days->count(),
            '1day'   => $expiring1Day->count(),
            'expired' => $expiredVms->count(),
        ]);
    }

    protected function notifyUser(VirtualMachine $vm, int $daysLeft): void
    {
        try {
            $user = $vm->user;

            if (!$user || !$user->email) {
                return;
            }

            SendEmailJob::dispatch($user->email, 'vm-expiry', [
                'vm_name'   => $vm->name,
                'days_left' => $daysLeft,
            ]);
        } catch (\Exception $e) {
            \Log::error('CheckExpiryJob::notifyUser failed', [
                'vm_id' => $vm->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function suspendVm(VirtualMachine $vm): void
    {
        try {
            $node = $vm->node;

            if (!$node || !$node->isOnline()) {
                $vm->update(['status' => 'suspended']);
                return;
            }

            $proxmox = new ProxmoxService();
            $proxmox->suspendVm($vm);

            $vm->update(['status' => 'suspended']);

            \Log::info('VM auto-suspended due to expiry', [
                'vm_id'   => $vm->id,
                'vmid'    => $vm->vm_id,
                'expired' => $vm->expires_at,
            ]);
        } catch (\Exception $e) {
            \Log::error('CheckExpiryJob::suspendVm failed', [
                'vm_id' => $vm->id,
                'error' => $e->getMessage(),
            ]);
            $vm->update(['status' => 'suspended']);
        }
    }
}
