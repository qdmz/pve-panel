<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CreateOrderRequest;
use App\Http\Requests\Api\PayOrderRequest;
use App\Models\Coupon;
use App\Models\Node;
use App\Models\Order;
use App\Models\Product;
use App\Models\Transaction;
use App\Services\EpayService;
use App\Services\VmProvisioningService;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        try {
            $orders = $request->user()->orders()
                           ->with('product:id,name,type')
                           ->orderBy('created_at', 'desc')
                           ->paginate(20);

            return ApiResponse::paginated($orders, 'Orders retrieved.');
        } catch (\Exception $e) {
            \Log::error('OrderController::index failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to retrieve orders.', 500);
        }
    }

    public function store(CreateOrderRequest $request)
    {
        try {
            $user    = $request->user();
            $product = Product::findOrFail($request->product_id);

            if ($product->status !== 'active') {
                return ApiResponse::error('This product is not currently available.', 400);
            }

            $amount = $product->getPriceForCycle($request->billing_cycle);

            if (!$amount) {
                return ApiResponse::error('Invalid billing cycle.', 400);
            }

            // Apply coupon
            $couponDiscount = 0;
            $couponId       = null;

            if ($request->coupon_code) {
                $coupon = Coupon::where('code', $request->coupon_code)->first();

                if (!$coupon || !$coupon->isAvailable()) {
                    return ApiResponse::error('Invalid or expired coupon code.', 400);
                }

                if (!$coupon->isUsableByUser($user->id)) {
                    return ApiResponse::error('You have reached the usage limit for this coupon.', 400);
                }

                if ($coupon->min_order_amount > 0 && $amount < $coupon->min_order_amount) {
                    return ApiResponse::error("Minimum order amount for this coupon is {$coupon->min_order_amount}.", 400);
                }

                $couponDiscount = $coupon->calculateDiscount($amount);
                $couponId       = $coupon->id;
            }

            $finalAmount = max(0, $amount - $couponDiscount);

            $order = Order::create([
                'order_no'        => Order::generateOrderNo(),
                'user_id'         => $user->id,
                'product_id'      => $product->id,
                'billing_cycle'   => $request->billing_cycle,
                'amount'          => $finalAmount,
                'discount'        => $couponDiscount,
                'coupon_id'       => $couponId,
                'payment_status'  => 'pending',
            ]);

            if ($couponId) {
                Coupon::where('id', $couponId)->increment('used_count');
            }

            // If free (100% coupon), auto-process
            if ($finalAmount <= 0) {
                $order->update([
                    'payment_status' => 'paid',
                    'payment_method' => 'balance',
                    'paid_at'        => now(),
                ]);

                app(VmProvisioningService::class)->provisionFromOrder($order);
            }

            return ApiResponse::success(['order' => $order], 'Order created successfully.', 201);
        } catch (\Exception $e) {
            \Log::error('OrderController::store failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return ApiResponse::error('Failed to create order.', 500);
        }
    }

    public function show(Request $request, Order $order)
    {
        try {
            if ($order->user_id !== $request->user()->id) {
                return ApiResponse::error('Unauthorized.', 403);
            }

            $order->load(['product', 'vm', 'coupon']);

            return ApiResponse::success(['order' => $order]);
        } catch (\Exception $e) {
            \Log::error('OrderController::show failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to retrieve order.', 500);
        }
    }

    public function pay(PayOrderRequest $request, Order $order)
    {
        try {
            if ($order->user_id !== $request->user()->id) {
                return ApiResponse::error('Unauthorized.', 403);
            }

            if ($order->payment_status !== 'pending') {
                return ApiResponse::error('Order is not in pending status.', 400);
            }

            if ($request->payment_method === 'balance') {
                $user = $request->user();

                if ($user->balance < $order->amount) {
                    return ApiResponse::error('Insufficient balance.', 402);
                }

                $balanceBefore = $user->balance;
                $user->decrement('balance', $order->amount);
                $balanceAfter = $user->balance;

                Transaction::create([
                    'user_id'        => $user->id,
                    'type'           => 'payment',
                    'amount'         => -$order->amount,
                    'balance_before' => $balanceBefore,
                    'balance_after'  => $balanceAfter,
                    'description'    => "Payment for order {$order->order_no}",
                    'reference_type' => 'order',
                    'reference_id'   => $order->id,
                ]);

                $order->update([
                    'payment_status' => 'paid',
                    'payment_method' => 'balance',
                    'paid_at'        => now(),
                ]);

                app(VmProvisioningService::class)->provisionFromOrder($order);

                return ApiResponse::success(['order' => $order], 'Payment successful. VM is being provisioned.');
            }

            // Epay payment
            $epay = new EpayService();

            if (!$epay->isConfigured()) {
                return ApiResponse::error('Payment gateway is not configured.', 500);
            }

            $result = $epay->createOrder($order, $request->get('payment_channel', 'alipay'));

            return ApiResponse::success([
                'order'       => $order,
                'payment_url' => $result['pay_url'] ?? '',
                'params'      => $result['params'] ?? [],
            ], 'Payment URL generated.');
        } catch (\Exception $e) {
            \Log::error('OrderController::pay failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Payment failed.', 500);
        }
    }

    public function cancel(Request $request, Order $order)
    {
        try {
            if ($order->user_id !== $request->user()->id) {
                return ApiResponse::error('Unauthorized.', 403);
            }

            if ($order->payment_status !== 'pending') {
                return ApiResponse::error('Only pending orders can be cancelled.', 400);
            }

            $order->update(['payment_status' => 'cancelled']);

            return ApiResponse::success(['order' => $order], 'Order cancelled.');
        } catch (\Exception $e) {
            \Log::error('OrderController::cancel failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to cancel order.', 500);
        }
    }

    protected function provisionVm(Order $order): void
    {
        app(VmProvisioningService::class)->provisionFromOrder($order);
    }
}
