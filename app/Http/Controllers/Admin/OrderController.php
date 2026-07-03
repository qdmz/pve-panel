<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Transaction;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        try {
            $orders = Order::with(['user:id,name,email', 'product:id,name'])
                ->when($request->status, function ($query, $status) {
                    return $query->byStatus($status);
                })
                ->when($request->date_from, function ($query, $date) {
                    return $query->whereDate('created_at', '>=', $date);
                })
                ->when($request->date_to, function ($query, $date) {
                    return $query->whereDate('created_at', '<=', $date);
                })
                ->when($request->search, function ($query, $search) {
                    return $query->where(function ($q) use ($search) {
                        $q->where('order_no', 'like', "%{$search}%")
                          ->orWhereHas('user', function ($uq) use ($search) {
                              $uq->where('name', 'like', "%{$search}%")
                                 ->orWhere('email', 'like', "%{$search}%");
                          });
                    });
                })
                ->orderBy('created_at', 'desc')
                ->paginate(20);

            return ApiResponse::paginated($orders, 'Orders retrieved.');
        } catch (\Exception $e) {
            \Log::error('Admin\\OrderController::index failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to retrieve orders.', 500);
        }
    }

    public function show(Order $order)
    {
        try {
            $order->load(['user:id,name,email', 'product', 'vm', 'coupon']);

            return ApiResponse::success(['order' => $order]);
        } catch (\Exception $e) {
            \Log::error('Admin\\OrderController::show failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to retrieve order.', 500);
        }
    }

    public function markPaid(Request $request, Order $order)
    {
        try {
            if ($order->status !== 'pending') {
                return ApiResponse::error('Order is not in pending status.', 400);
            }

            $transactionId = $request->input('transaction_id', 'MANUAL-' . date('YmdHis'));

            $order->update([
                'status'         => 'paid',
                'payment_method' => 'manual',
                'transaction_id' => $transactionId,
                'paid_at'        => now(),
            ]);

            Transaction::create([
                'user_id'        => $order->user_id,
                'type'           => 'payment',
                'amount'         => -$order->amount,
                'balance_before' => $order->user->balance,
                'balance_after'  => $order->user->balance,
                'description'    => "Manual payment for {$order->order_no}",
                'reference_type' => 'order',
                'reference_id'   => $order->id,
                'transaction_id' => $transactionId,
            ]);

            return ApiResponse::success(['order' => $order], 'Order marked as paid.');
        } catch (\Exception $e) {
            \Log::error('Admin\\OrderController::markPaid failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to mark order as paid.', 500);
        }
    }

    public function refund(Request $request, Order $order)
    {
        try {
            if ($order->status !== 'paid') {
                return ApiResponse::error('Only paid orders can be refunded.', 400);
            }

            // Refund to user balance
            $user = $order->user;
            $balanceBefore = $user->balance;
            $user->increment('balance', $order->amount);
            $balanceAfter = $user->balance;

            Transaction::create([
                'user_id'        => $user->id,
                'type'           => 'refund',
                'amount'         => $order->amount,
                'balance_before' => $balanceBefore,
                'balance_after'  => $balanceAfter,
                'description'    => "Refund for order {$order->order_no}",
                'reference_type' => 'order',
                'reference_id'   => $order->id,
            ]);

            $order->update(['status' => 'refunded']);

            return ApiResponse::success(['order' => $order], 'Refund processed.');
        } catch (\Exception $e) {
            \Log::error('Admin\\OrderController::refund failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to process refund.', 500);
        }
    }

    public function stats()
    {
        try {
            $todayRevenue = Order::paid()->whereDate('paid_at', today())->sum('amount');
            $weekRevenue  = Order::paid()->whereBetween('paid_at', [now()->startOfWeek(), now()->endOfWeek()])->sum('amount');
            $monthRevenue = Order::paid()->whereMonth('paid_at', now()->month)->sum('amount');

            $totalOrders       = Order::count();
            $pendingOrders     = Order::pending()->count();
            $paidOrders        = Order::paid()->count();
            $refundedOrders    = Order::where('status', 'refunded')->count();

            $byMethod = Order::paid()
                ->selectRaw('payment_method, COUNT(*) as count, SUM(amount) as total')
                ->groupBy('payment_method')
                ->get()
                ->keyBy('payment_method');

            return ApiResponse::success([
                'revenue' => [
                    'today' => (float) $todayRevenue,
                    'week'  => (float) $weekRevenue,
                    'month' => (float) $monthRevenue,
                ],
                'counts' => [
                    'total'    => $totalOrders,
                    'pending'  => $pendingOrders,
                    'paid'     => $paidOrders,
                    'refunded' => $refundedOrders,
                ],
                'by_method' => $byMethod,
            ]);
        } catch (\Exception $e) {
            \Log::error('Admin\\OrderController::stats failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to retrieve order stats.', 500);
        }
    }
}
