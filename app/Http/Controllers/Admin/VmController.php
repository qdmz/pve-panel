<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BatchVmRequest;
use App\Http\Requests\Admin\UpdateVmRequest;
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
            $vms = VirtualMachine::with(['user:id,name,email', 'node:id,name'])
                ->when($request->search, function ($query, $search) {
                    return $query->where(function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%")
                          ->orWhere('vm_id', 'like', "%{$search}%")
                          ->orWhere('ip', 'like', "%{$search}%");
                    });
                })
                ->when($request->status, function ($query, $status) {
                    return $query->where('status', $status);
                })
                ->when($request->type, function ($query, $type) {
                    return $query->where('type', $type);
                })
                ->when($request->user_id, function ($query, $userId) {
                    return $query->where('user_id', $userId);
                })
                ->orderBy('created_at', 'desc')
                ->paginate(20);

            return ApiResponse::paginated($vms, 'VMs retrieved.');
        } catch (\Exception $e) {
            \Log::error('Admin\\VmController::index failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to retrieve VMs.', 500);
        }
    }

    public function show(VirtualMachine $vm)
    {
        try {
            $vm->load([
                'user:id,name,email',
                'node:id,name,status',
                'product:id,name',
                'snapshots',
                'natRules',
                'domains',
            ]);

            return ApiResponse::success(['vm' => $vm]);
        } catch (\Exception $e) {
            \Log::error('Admin\\VmController::show failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to retrieve VM.', 500);
        }
    }

    public function update(UpdateVmRequest $request, VirtualMachine $vm)
    {
        try {
            $vm->update($request->validated());

            return ApiResponse::success(['vm' => $vm], 'VM updated.');
        } catch (\Exception $e) {
            \Log::error('Admin\\VmController::update failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to update VM.', 500);
        }
    }

    public function start(VirtualMachine $vm)
    {
        try {
            if ($vm->status === 'running') {
                return ApiResponse::error('VM is already running.', 400);
            }

            $proxmox = $this->getProxmoxService($vm);
            $proxmox->startVm($vm);
            $vm->update(['status' => 'running']);

            return ApiResponse::success(['vm' => $vm], 'VM started.');
        } catch (\Exception $e) {
            \Log::error('Admin\\VmController::start failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to start VM.', 502);
        }
    }

    public function stop(VirtualMachine $vm)
    {
        try {
            if ($vm->status === 'stopped') {
                return ApiResponse::error('VM is already stopped.', 400);
            }

            $proxmox = $this->getProxmoxService($vm);
            $proxmox->stopVm($vm);
            $vm->update(['status' => 'stopped']);

            return ApiResponse::success(['vm' => $vm], 'VM stopped.');
        } catch (\Exception $e) {
            \Log::error('Admin\\VmController::stop failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to stop VM.', 502);
        }
    }

    public function restart(VirtualMachine $vm)
    {
        try {
            $proxmox = $this->getProxmoxService($vm);
            $proxmox->restartVm($vm);
            $vm->update(['status' => 'running']);

            return ApiResponse::success(['vm' => $vm], 'VM restarted.');
        } catch (\Exception $e) {
            \Log::error('Admin\\VmController::restart failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to restart VM.', 502);
        }
    }

    public function suspend(VirtualMachine $vm)
    {
        try {
            if ($vm->status === 'suspended') {
                return ApiResponse::error('VM is already suspended.', 400);
            }

            $proxmox = $this->getProxmoxService($vm);
            $proxmox->suspendVm($vm);
            $vm->update(['status' => 'suspended']);

            return ApiResponse::success(['vm' => $vm], 'VM suspended.');
        } catch (\Exception $e) {
            \Log::error('Admin\\VmController::suspend failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to suspend VM.', 502);
        }
    }

    public function unsuspend(VirtualMachine $vm)
    {
        try {
            if ($vm->status !== 'suspended') {
                return ApiResponse::error('VM is not suspended.', 400);
            }

            $proxmox = $this->getProxmoxService($vm);
            $proxmox->resumeVm($vm);
            $vm->update(['status' => 'running']);

            return ApiResponse::success(['vm' => $vm], 'VM unsuspended.');
        } catch (\Exception $e) {
            \Log::error('Admin\\VmController::unsuspend failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to unsuspend VM.', 502);
        }
    }

    public function destroy(VirtualMachine $vm)
    {
        try {
            $proxmox = $this->getProxmoxService($vm);
            $proxmox->deleteVm($vm);

            $vm->snapshots()->delete();
            $vm->natRules()->delete();
            $vm->domains()->delete();
            $vm->forceDelete();

            return ApiResponse::success(null, 'VM force deleted.');
        } catch (\Exception $e) {
            \Log::error('Admin\\VmController::destroy failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to delete VM.', 502);
        }
    }

    public function batch(BatchVmRequest $request)
    {
        try {
            $action = $request->action;
            $ids    = $request->ids;
            $results = [];

            $vms = VirtualMachine::whereIn('id', $ids)->get();

            foreach ($vms as $vm) {
                try {
                    $proxmox = $this->getProxmoxService($vm);

                    switch ($action) {
                        case 'start':
                            $proxmox->startVm($vm);
                            $vm->update(['status' => 'running']);
                            break;
                        case 'stop':
                            $proxmox->stopVm($vm);
                            $vm->update(['status' => 'stopped']);
                            break;
                        case 'restart':
                            $proxmox->restartVm($vm);
                            $vm->update(['status' => 'running']);
                            break;
                        case 'delete':
                            $proxmox->deleteVm($vm);
                            $vm->snapshots()->delete();
                            $vm->natRules()->delete();
                            $vm->domains()->delete();
                            $vm->forceDelete();
                            break;
                    }

                    $results[$vm->id] = 'success';
                } catch (\Exception $e) {
                    $results[$vm->id] = 'failed: ' . $e->getMessage();
                }
            }

            return ApiResponse::success(['results' => $results], 'Batch operation completed.');
        } catch (\Exception $e) {
            \Log::error('Admin\\VmController::batch failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to execute batch operation.', 500);
        }
    }
}
