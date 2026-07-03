<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\Request;

class BillingController extends Controller
{
    public function index(Request $request)
    {
        try {
            $user = $request->user();

            $monthlySpend = Payment::where('user_id', $user->id)
                                 ->where('status', 'success')
                                 ->whereMonth('created_at', now()->month)
                                 ->sum('amount');

            $pendingOrders = $user->orders()->where('payment_status', 'pending')->count();

            $expiringVms = $user->virtualMachines()
                              ->where('expires_at', '<=', now()->addDays(7))
                              ->where('status', '!=', 'deleted')
                              ->count();

            return ApiResponse::success([
                'balance'        => (float) $user->balance,
                'monthly_spend'  => abs((float) $monthlySpend),
                'pending_orders' => $pendingOrders,
                'expiring_vms'   => $expiringVms,
            ]);
        } catch (\Exception $e) {
            \Log::error('BillingController::index failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to retrieve billing summary.', 500);
        }
    }

    public function transactions(Request $request)
    {
        try {
            $payments = Payment::where('user_id', $request->user()->id)
                                 ->orderBy('created_at', 'desc')
                                 ->paginate(20);

            return ApiResponse::paginated($payments, 'Transactions retrieved.');
        } catch (\Exception $e) {
            \Log::error('BillingController::transactions failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to retrieve transactions.', 500);
        }
    }

    public function expiring(Request $request)
    {
        try {
            $vms = $request->user()->virtualMachines()
                        ->where('expires_at', '<=', now()->addDays(30))
                        ->where('status', '!=', 'deleted')
                        ->select('id', 'name', 'hostname', 'type', 'status', 'expires_at')
                        ->orderBy('expires_at', 'asc')
                        ->get();

            return ApiResponse::success(['vms' => $vms], 'Expiring VMs retrieved.');
        } catch (\Exception $e) {
            \Log::error('BillingController::expiring failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to retrieve expiring VMs.', 500);
        }
    }
}

