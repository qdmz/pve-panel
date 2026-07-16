<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\RechargeRequest;
use App\Models\Coupon;
use App\Models\Node;
use App\Models\Order;
use App\Models\Transaction;
use App\Services\EpayService;
use App\Services\VmProvisioningService;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function recharge(RechargeRequest $request)
    {
        try {
            $user   = $request->user();
            $amount = $request->amount;

            // Apply coupon
            $couponDiscount = 0;
            $couponId       = null;

            if ($request->coupon_code) {
                $coupon = Coupon::where('code', $request->coupon_code)->first();

                if ($coupon && $coupon->isAvailable() && $coupon->isUsableByUser($user->id)) {
                    if (!$coupon->min_order_amount || $amount >= $coupon->min_order_amount) {
                        $couponDiscount = $coupon->calculateDiscount($amount);
                        $couponId       = $coupon->id;

                        Coupon::where('id', $couponId)->increment('used_count');
                    }
                }
            }

            $actualPay = max(0.01, $amount - $couponDiscount);

            $orderNo = 'RECHARGE-' . date('YmdHis') . rand(1000, 9999);

            $transaction = Transaction::create([
                'user_id'        => $user->id,
                'type'           => 'recharge',
                'amount'         => $amount,
                'balance_before' => $user->balance,
                'balance_after'  => $user->balance,
                'description'    => "Recharge - {$orderNo}",
                'reference_type' => 'recharge',
                'reference_id'   => 0,
            ]);

            $epay = new EpayService();

            $result = $epay->createRecharge($user->id, $actualPay, $request->get('payment_channel', 'alipay'));

            return ApiResponse::success([
                'payment_url'   => $result['pay_url'] ?? '',
                'params'        => $result['params'] ?? [],
                'transaction'   => $transaction,
                'order_no'      => $orderNo,
                'actual_amount' => $actualPay,
            ], 'Payment URL generated.');
        } catch (\Exception $e) {
            \Log::error('PaymentController::recharge failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to create recharge.', 500);
        }
    }

    public function notify(Request $request)
    {
        \Log::info('Epay notify received', $request->all());

        try {
            $epay = new EpayService();

            if (!$epay->verifySign($request->all())) {
                \Log::warning('Epay notify: invalid signature');
                return 'fail';
            }

            $tradeStatus = $request->input('trade_status', '');
            if ($tradeStatus !== 'TRADE_SUCCESS') {
                return 'success';
            }

            $orderNo  = $request->input('out_trade_no');
            $money    = (float) $request->input('money', 0);
            $tradeNo  = $request->input('trade_no', '');

            if (str_starts_with($orderNo, 'RECHARGE-')) {
                // Recharge payment
                $transaction = Transaction::where('description', 'like', "%{$orderNo}%")
                    ->where('type', 'recharge')
                    ->first();

                if ($transaction && $transaction->balance_before === $transaction->balance_after) {
                    $user = $transaction->user;

                    $balanceBefore = $user->balance;
                    $user->increment('balance', $transaction->amount);
                    $balanceAfter = $user->balance;

                    $transaction->update([
                        'balance_before'  => $balanceBefore + $transaction->amount,
                        'balance_after'   => $balanceAfter,
                        'transaction_id'  => $tradeNo,
                    ]);
                }
            } elseif (str_starts_with($orderNo, 'ORD-') || str_starts_with($orderNo, 'RENEW-')) {
                // Order payment
                $order = Order::where('order_no', $orderNo)->first();

                if ($order && $order->payment_status === 'pending') {
                    $order->update([
                        'payment_status' => 'paid',
                        'payment_method' => 'epay',
                        'transaction_id' => $tradeNo,
                        'paid_at'        => now(),
                    ]);

                    // Transaction record
                    Transaction::create([
                        'user_id'        => $order->user_id,
                        'type'           => 'payment',
                        'amount'         => -$order->amount,
                        'balance_before' => $order->user->balance,
                        'balance_after'  => $order->user->balance,
                        'description'    => "Epay payment for {$order->order_no}",
                        'reference_type' => 'order',
                        'reference_id'   => $order->id,
                        'transaction_id' => $tradeNo,
                    ]);

                    // Trigger VM provisioning via shared service
                    \Log::info('Dispatching VM provisioning after Epay callback', [
                        'order_no' => $orderNo,
                        'product_id' => $order->product_id,
                    ]);
                    app(VmProvisioningService::class)->provisionFromOrder($order);
                }
            }

            return 'success';
        } catch (\Exception $e) {
            \Log::error('PaymentController::notify failed', ['error' => $e->getMessage()]);
            return 'fail';
        }
    }

    public function notifyPost(Request $request)
    {
        return $this->notify($request);
    }
}
