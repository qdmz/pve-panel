<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AddDomainRequest;
use App\Http\Requests\Api\AddNatRuleRequest;
use App\Http\Requests\Api\CreateSnapshotRequest;
use App\Http\Requests\Api\VmReinstallRequest;
use App\Http\Requests\Api\VmRenewRequest;
use App\Http\Requests\Api\VmResetPasswordRequest;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\VirtualMachine;
use App\Services\ProxmoxService;
use Illuminate\Http\Request;

class VmController extends Controller
{
    protected function getProxmoxService(VirtualMachine $vm): ProxmoxService
    {
        $service = new ProxmoxService();
        if ($vm->node) {
            $service->configure($vm->node);
            $service->authenticate();
        }
        return $service;
    }

    public function index(Request $request)
    {
        try {
            $vms = $request->user()->virtualMachines()
                        ->with('node:id,name,status')
                        ->orderBy('created_at', 'desc')
                        ->paginate(20);

            return ApiResponse::paginated($vms, 'VMs retrieved.');
        } catch (\Exception $e) {
            \Log::error('VmController::index failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to retrieve VMs.', 500);
        }
    }

    public function show(Request $request, VirtualMachine $vm)
    {
        try {
            if ($vm->user_id !== $request->user()->id && !$request->user()->isAdmin()) {
                return ApiResponse::error('Unauthorized.', 403);
            }

            $vm->load([
                'node:id,name,status',
                'snapshots' => function ($q) { $q->orderBy('created_at', 'desc')->limit(10); },
                'natRules',
                'domains',
            ]);

            return ApiResponse::success(['vm' => $vm]);
        } catch (\Exception $e) {
            \Log::error('VmController::show failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to retrieve VM details.', 500);
        }
    }

    public function start(Request $request, VirtualMachine $vm)
    {
        try {
            if ($vm->user_id !== $request->user()->id) {
                return ApiResponse::error('Unauthorized.', 403);
            }

            if ($vm->status === 'running') {
                return ApiResponse::error('VM is already running.', 400);
            }

            $proxmox = $this->getProxmoxService($vm);
            $proxmox->startVm($vm->node->name, $vm->vmid);

            $vm->update(['status' => 'running']);

            return ApiResponse::success(['vm' => $vm], 'VM started successfully.');
        } catch (\Exception $e) {
            \Log::error('VmController::start failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to start VM.', 502);
        }
    }

    public function stop(Request $request, VirtualMachine $vm)
    {
        try {
            if ($vm->user_id !== $request->user()->id) {
                return ApiResponse::error('Unauthorized.', 403);
            }

            if ($vm->status === 'stopped') {
                return ApiResponse::error('VM is already stopped.', 400);
            }

            $proxmox = $this->getProxmoxService($vm);
            $proxmox->stopVm($vm->node->name, $vm->vmid);

            $vm->update(['status' => 'stopped']);

            return ApiResponse::success(['vm' => $vm], 'VM stopped successfully.');
        } catch (\Exception $e) {
            \Log::error('VmController::stop failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to stop VM.', 502);
        }
    }

    public function restart(Request $request, VirtualMachine $vm)
    {
        try {
            if ($vm->user_id !== $request->user()->id) {
                return ApiResponse::error('Unauthorized.', 403);
            }

            $proxmox = $this->getProxmoxService($vm);
            $proxmox->restartVm($vm->node->name, $vm->vmid);

            $vm->update(['status' => 'running']);

            return ApiResponse::success(['vm' => $vm], 'VM restarted successfully.');
        } catch (\Exception $e) {
            \Log::error('VmController::restart failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to restart VM.', 502);
        }
    }

    public function resetPassword(VmResetPasswordRequest $request, VirtualMachine $vm)
    {
        try {
            if ($vm->user_id !== $request->user()->id) {
                return ApiResponse::error('Unauthorized.', 403);
            }

            $proxmox = $this->getProxmoxService($vm);
            $proxmox->resetVmPassword($vm->node->name, $vm->vmid, 'root', $request->new_password);

            $vm->update(['password' => $request->new_password]);

            return ApiResponse::success(null, 'Password reset successfully.');
        } catch (\Exception $e) {
            \Log::error('VmController::resetPassword failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to reset password.', 502);
        }
    }

    public function reinstall(VmReinstallRequest $request, VirtualMachine $vm)
    {
        try {
            if ($vm->user_id !== $request->user()->id) {
                return ApiResponse::error('Unauthorized.', 403);
            }

            if ($vm->status !== 'stopped') {
                return ApiResponse::error('VM must be stopped before reinstalling.', 400);
            }

            $proxmox = $this->getProxmoxService($vm);
            $proxmox->reinstallVm($vm->node->name, $vm->vmid, [
                'ide2' => "local:iso/{$request->template_id},media=cdrom",
                'boot' => 'order=ide2',
            ]);

            $vm->update([
                'os_template' => $request->template_id,
                'status'      => 'installing',
            ]);

            return ApiResponse::success(['vm' => $vm], 'Reinstall initiated. This may take a few minutes.');
        } catch (\Exception $e) {
            \Log::error('VmController::reinstall failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to reinstall VM.', 502);
        }
    }

    public function vnc(Request $request, VirtualMachine $vm)
    {
        try {
            if ($vm->user_id !== $request->user()->id) {
                return ApiResponse::error('Unauthorized.', 403);
            }

            if ($vm->status !== 'running') {
                return ApiResponse::error('VM must be running to access VNC.', 400);
            }

            $proxmox = $this->getProxmoxService($vm);
            $vncInfo = $proxmox->getVncProxy($vm->node->name, $vm->vmid);

            if (!$vncInfo) {
                return ApiResponse::error('Failed to get VNC proxy.', 502);
            }

            return ApiResponse::success(['vnc' => $vncInfo]);
        } catch (\Exception $e) {
            \Log::error('VmController::vnc failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to get VNC connection.', 502);
        }
    }

    public function metrics(Request $request, VirtualMachine $vm)
    {
        try {
            if ($vm->user_id !== $request->user()->id) {
                return ApiResponse::error('Unauthorized.', 403);
            }

            $timeframe = $request->get('timeframe', 'hour');
            $proxmox   = $this->getProxmoxService($vm);
            $rrdData   = $proxmox->getVmRrdData($vm->node->name, $vm->vmid, $timeframe);

            return ApiResponse::success(['metrics' => $rrdData]);
        } catch (\Exception $e) {
            \Log::error('VmController::metrics failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to retrieve metrics.', 502);
        }
    }

    public function renew(VmRenewRequest $request, VirtualMachine $vm)
    {
        try {
            if ($vm->user_id !== $request->user()->id) {
                return ApiResponse::error('Unauthorized.', 403);
            }

            $product = $vm->product;
            $amount  = $product->getPriceForCycle($request->billing_cycle);

            if (!$amount) {
                return ApiResponse::error('Invalid billing cycle.', 400);
            }

            // Apply coupon
            $couponDiscount = 0;
            $couponId       = null;
            $couponCode     = $request->coupon_code;

            if ($couponCode) {
                $coupon = Coupon::active()->where('code', $couponCode)->first();

                if (!$coupon) {
                    return ApiResponse::error('Invalid or expired coupon code.', 400);
                }

                if (!$coupon->isAvailable()) {
                    return ApiResponse::error('Coupon is no longer available.', 400);
                }

                if (!$coupon->isUsableByUser($request->user()->id)) {
                    return ApiResponse::error('You have reached the usage limit for this coupon.', 400);
                }

                if ($coupon->min_amount && $amount < $coupon->min_amount) {
                    return ApiResponse::error("Minimum order amount for this coupon is {$coupon->min_amount}.", 400);
                }

                if ($coupon->type === 'fixed') {
                    $couponDiscount = $coupon->value;
                } else {
                    $couponDiscount = $amount * ($coupon->value / 100);
                }

                $couponDiscount = min($couponDiscount, $amount);
                $couponId       = $coupon->id;
            }

            $finalAmount = max(0, $amount - $couponDiscount);

            $order = Order::create([
                'order_no'        => 'RENEW-' . date('YmdHis') . rand(1000, 9999),
                'user_id'         => $request->user()->id,
                'product_id'      => $product->id,
                'vm_id'           => $vm->id,
                'billing_cycle'   => $request->billing_cycle,
                'amount'          => $finalAmount,
                'original_amount' => $amount,
                'coupon_id'       => $couponId,
                'coupon_discount' => $couponDiscount,
                'status'          => 'pending',
            ]);

            if ($couponId) {
                Coupon::where('id', $couponId)->increment('used_count');
            }

            return ApiResponse::success(['order' => $order], 'Renewal order created.');
        } catch (\Exception $e) {
            \Log::error('VmController::renew failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to create renewal order.', 500);
        }
    }

    public function destroy(Request $request, VirtualMachine $vm)
    {
        try {
            if ($vm->user_id !== $request->user()->id) {
                return ApiResponse::error('Unauthorized.', 403);
            }

            if ($vm->status !== 'stopped') {
                return ApiResponse::error('VM must be stopped before deletion.', 400);
            }

            $proxmox = $this->getProxmoxService($vm);
            $proxmox->deleteVm($vm->node->name, $vm->vmid);

            $vm->snapshots()->delete();
            $vm->natRules()->delete();
            $vm->domains()->delete();
            $vm->delete();

            return ApiResponse::success(null, 'VM deleted successfully.');
        } catch (\Exception $e) {
            \Log::error('VmController::destroy failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to delete VM.', 502);
        }
    }

    // Snapshot endpoints

    public function snapshots(Request $request, VirtualMachine $vm)
    {
        try {
            if ($vm->user_id !== $request->user()->id) {
                return ApiResponse::error('Unauthorized.', 403);
            }

            $snapshots = $vm->snapshots()->orderBy('created_at', 'desc')->get();

            return ApiResponse::success(['snapshots' => $snapshots]);
        } catch (\Exception $e) {
            \Log::error('VmController::snapshots failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to retrieve snapshots.', 500);
        }
    }

    public function createSnapshot(CreateSnapshotRequest $request, VirtualMachine $vm)
    {
        try {
            if ($vm->user_id !== $request->user()->id) {
                return ApiResponse::error('Unauthorized.', 403);
            }

            $snapname = 'snap_' . date('Ymd_His');
            $proxmox  = $this->getProxmoxService($vm);

            $result = $proxmox->createSnapshot(
                $vm->node->name,
                $vm->vmid,
                $snapname,
                $request->description ?? ''
            );

            $snapshot = $vm->snapshots()->create([
                'name'          => $request->name,
                'description'   => $request->description,
                'snapshot_name' => $snapname,
            ]);

            return ApiResponse::success(['snapshot' => $snapshot], 'Snapshot created successfully.', 201);
        } catch (\Exception $e) {
            \Log::error('VmController::createSnapshot failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to create snapshot.', 502);
        }
    }

    public function deleteSnapshot(Request $request, VirtualMachine $vm, $snapshotId)
    {
        try {
            if ($vm->user_id !== $request->user()->id) {
                return ApiResponse::error('Unauthorized.', 403);
            }

            $snapshot = $vm->snapshots()->findOrFail($snapshotId);

            $proxmox = $this->getProxmoxService($vm);
            $proxmox->deleteSnapshot($vm->node->name, $vm->vmid, $snapshot->snapshot_name);

            $snapshot->delete();

            return ApiResponse::success(null, 'Snapshot deleted.');
        } catch (\Exception $e) {
            \Log::error('VmController::deleteSnapshot failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to delete snapshot.', 502);
        }
    }

    public function restoreSnapshot(Request $request, VirtualMachine $vm, $snapshotId)
    {
        try {
            if ($vm->user_id !== $request->user()->id) {
                return ApiResponse::error('Unauthorized.', 403);
            }

            $snapshot = $vm->snapshots()->findOrFail($snapshotId);

            if ($vm->status !== 'stopped') {
                return ApiResponse::error('VM must be stopped before restoring a snapshot.', 400);
            }

            $proxmox = $this->getProxmoxService($vm);
            $proxmox->restoreSnapshot($vm->node->name, $vm->vmid, $snapshot->snapshot_name);

            return ApiResponse::success(null, 'Snapshot restored.');
        } catch (\Exception $e) {
            \Log::error('VmController::restoreSnapshot failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to restore snapshot.', 502);
        }
    }

    // NAT endpoints

    public function natRules(Request $request, VirtualMachine $vm)
    {
        try {
            if ($vm->user_id !== $request->user()->id) {
                return ApiResponse::error('Unauthorized.', 403);
            }

            $rules = $vm->natRules()->orderBy('created_at', 'desc')->get();

            return ApiResponse::success(['nat_rules' => $rules]);
        } catch (\Exception $e) {
            \Log::error('VmController::natRules failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to retrieve NAT rules.', 500);
        }
    }

    public function addNatRule(AddNatRuleRequest $request, VirtualMachine $vm)
    {
        try {
            if ($vm->user_id !== $request->user()->id) {
                return ApiResponse::error('Unauthorized.', 403);
            }

            $rule = $vm->natRules()->create($request->validated());

            // Apply iptables rule on the PVE host
            try {
                $node = $vm->node;
                $svc = app(\App\Services\ProxmoxService::class);
                $svc->addPortForward(
                    $node,
                    (string) $rule->public_port,
                    $vm->nat_ipv4 ?: $vm->ip,
                    (string) $rule->local_port,
                    $rule->protocol
                );
            } catch (\Exception $e) {
                \Log::warning('NatRule saved but iptables apply failed', ['error' => $e->getMessage()]);
            }

            return ApiResponse::success(['nat_rule' => $rule], 'NAT rule added.', 201);
        } catch (\Exception $e) {
            \Log::error('VmController::addNatRule failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to add NAT rule.', 500);
        }
    }

    public function deleteNatRule(Request $request, VirtualMachine $vm, $ruleId)
    {
        try {
            if ($vm->user_id !== $request->user()->id) {
                return ApiResponse::error('Unauthorized.', 403);
            }

            $rule = $vm->natRules()->findOrFail($ruleId);
            $rule->delete();

            return ApiResponse::success(null, 'NAT rule deleted.');
        } catch (\Exception $e) {
            \Log::error('VmController::deleteNatRule failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to delete NAT rule.', 500);
        }
    }

    // Domain endpoints

    public function domains(Request $request, VirtualMachine $vm)
    {
        try {
            if ($vm->user_id !== $request->user()->id) {
                return ApiResponse::error('Unauthorized.', 403);
            }

            $domains = $vm->domains()->orderBy('created_at', 'desc')->get();

            return ApiResponse::success(['domains' => $domains]);
        } catch (\Exception $e) {
            \Log::error('VmController::domains failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to retrieve domains.', 500);
        }
    }

    public function addDomain(AddDomainRequest $request, VirtualMachine $vm)
    {
        try {
            if ($vm->user_id !== $request->user()->id) {
                return ApiResponse::error('Unauthorized.', 403);
            }

            $domain = $vm->domains()->create($request->validated());

            return ApiResponse::success(['domain' => $domain], 'Domain added.', 201);
        } catch (\Exception $e) {
            \Log::error('VmController::addDomain failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to add domain.', 500);
        }
    }

    public function deleteDomain(Request $request, VirtualMachine $vm, $domainId)
    {
        try {
            if ($vm->user_id !== $request->user()->id) {
                return ApiResponse::error('Unauthorized.', 403);
            }

            $domain = $vm->domains()->findOrFail($domainId);
            $domain->delete();

            return ApiResponse::success(null, 'Domain deleted.');
        } catch (\Exception $e) {
            \Log::error('VmController::deleteDomain failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to delete domain.', 500);
        }
    }
}
