<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\IpAddress;
use App\Models\IpPool;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class IpPoolController extends Controller
{
    public function index(Request $request)
    {
        try {
            $pools = IpPool::with('node:id,name')
                ->when($request->node_id, fn($q, $v) => $q->where('node_id', $v))
                ->orderBy('created_at', 'desc')
                ->paginate(20);

            return ApiResponse::paginated($pools, 'IP pools retrieved.');
        } catch (\Exception $e) {
            \Log::error('IpPoolController::index failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to retrieve IP pools.', 500);
        }
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'node_id'          => 'required|exists:nodes,id',
            'type'             => 'required|in:ipv4,ipv6',
            'subnet'           => 'required|string|max:45',
            'gateway'          => 'nullable|string|max:45',
            'bridge'           => 'nullable|string|max:50',
            'dhcp_range_start' => 'nullable|string|max:45',
            'dhcp_range_end'   => 'nullable|string|max:45',
            'description'      => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error($validator->errors()->first(), 422);
        }

        try {
            $pool = IpPool::create($validator->validated());

            // Auto-generate IP addresses from subnet
            $this->generateAddresses($pool);

            return ApiResponse::success(['pool' => $pool], 'IP pool created.');
        } catch (\Exception $e) {
            \Log::error('IpPoolController::store failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to create IP pool.', 500);
        }
    }

    public function show(IpPool $pool)
    {
        try {
            $pool->load('node:id,name', 'addresses');
            return ApiResponse::success(['pool' => $pool]);
        } catch (\Exception $e) {
            \Log::error('IpPoolController::show failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to retrieve IP pool.', 500);
        }
    }

    public function update(Request $request, IpPool $pool)
    {
        $validator = Validator::make($request->all(), [
            'gateway'          => 'nullable|string|max:45',
            'bridge'           => 'nullable|string|max:50',
            'dhcp_range_start' => 'nullable|string|max:45',
            'dhcp_range_end'   => 'nullable|string|max:45',
            'description'      => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error($validator->errors()->first(), 422);
        }

        try {
            $pool->update($validator->validated());
            return ApiResponse::success(['pool' => $pool], 'IP pool updated.');
        } catch (\Exception $e) {
            \Log::error('IpPoolController::update failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to update IP pool.', 500);
        }
    }

    public function destroy(IpPool $pool)
    {
        try {
            $pool->addresses()->delete();
            $pool->delete();
            return ApiResponse::success(null, 'IP pool deleted.');
        } catch (\Exception $e) {
            \Log::error('IpPoolController::destroy failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to delete IP pool.', 500);
        }
    }

    public function addresses(IpPool $pool)
    {
        try {
            $pool->load('addresses');
            return ApiResponse::success(['addresses' => $pool->addresses]);
        } catch (\Exception $e) {
            \Log::error('IpPoolController::addresses failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to retrieve addresses.', 500);
        }
    }

    public function allocate(Request $request, IpPool $pool)
    {
        $validator = Validator::make($request->all(), [
            'vm_id' => 'required|integer|exists:virtual_machines,id',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error($validator->errors()->first(), 422);
        }

        try {
            $addr = IpAddress::where('pool_id', $pool->id)
                ->where('status', 'free')
                ->first();

            if (!$addr) {
                return ApiResponse::error('No free IP addresses available in this pool.', 400);
            }

            $addr->update([
                'vm_id'         => $request->vm_id,
                'status'        => 'allocated',
                'allocated_at'  => now(),
            ]);

            return ApiResponse::success(['address' => $addr], 'IP address allocated.');
        } catch (\Exception $e) {
            \Log::error('IpPoolController::allocate failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to allocate IP.', 500);
        }
    }

    public function release(IpAddress $ipAddress)
    {
        try {
            $ipAddress->update([
                'vm_id'        => null,
                'status'       => 'free',
                'allocated_at' => null,
            ]);

            return ApiResponse::success(['address' => $ipAddress], 'IP address released.');
        } catch (\Exception $e) {
            \Log::error('IpPoolController::release failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to release IP.', 500);
        }
    }

    private function generateAddresses(IpPool $pool): void
    {
        $subnet = $pool->subnet;
        $parts = explode('/', $subnet);
        if (count($parts) !== 2) return;

        $ip = $parts[0];
        $prefix = (int) $parts[1];

        if ($pool->type === 'ipv4') {
            $startIp = $pool->dhcp_range_start ?? long2ip(ip2long($ip) + 10);
            $endIp   = $pool->dhcp_range_end   ?? long2ip(ip2long($ip) + 250);

            $startLong = ip2long($startIp);
            $endLong   = ip2long($endIp);

            if ($startLong === false || $endLong === false) return;

            // Generate up to 200 addresses max
            $max = min($endLong - $startLong + 1, 200);
            $batch = [];

            for ($i = 0; $i < $max; $i++) {
                $batch[] = [
                    'pool_id'    => $pool->id,
                    'ip_address' => long2ip($startLong + $i),
                    'status'     => 'free',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            if (!empty($batch)) {
                IpAddress::insert($batch);
            }
        }
    }
}
