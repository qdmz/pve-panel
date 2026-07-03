<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Setting;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EpayService
{
    private string $apiUrl;
    private string $merchantId;
    private string $merchantKey;
    private string $notifyUrl;
    private string $returnUrl;

    public function __construct()
    {
        $this->apiUrl = rtrim(Setting::getValue('epay_api_url', ''), '/');
        $this->merchantId = Setting::getValue('epay_merchant_id', '');
        $this->merchantKey = Setting::getValue('epay_merchant_key', '');
        $this->notifyUrl = Setting::getValue('epay_notify_url', url('/api/payment/epay/notify'));
        $this->returnUrl = Setting::getValue('epay_return_url', url('/user/payment/result'));
    }

    /**
     * Create an Epay payment order for an existing Order model.
     */
    public function createOrder(Order $order, string $paymentMethod = 'alipay'): array
    {
        $params = [
            'pid' => (int) $this->merchantId,
            'type' => $this->mapPaymentType($paymentMethod),
            'out_trade_no' => $order->order_no,
            'notify_url' => $this->notifyUrl,
            'return_url' => $this->returnUrl,
            'name' => 'VM订购 #' . $order->order_no,
            'money' => number_format($order->amount, 2, '.', ''),
            'clientip' => request()->ip() ?? '127.0.0.1',
            'param' => $order->order_no,
        ];

        $params['sign'] = $this->generateSign($params);
        $params['sign_type'] = 'MD5';

        $submitUrl = $this->apiUrl . '/submit.php';

        Log::info('Epay order created', [
            'order_no' => $order->order_no,
            'amount' => $order->amount,
            'params' => $params,
        ]);

        return [
            'success' => true,
            'pay_url' => $submitUrl,
            'params' => $params,
        ];
    }

    /**
     * Create a recharge payment.
     */
    public function createRecharge(int $userId, float $amount, string $paymentMethod = 'alipay'): array
    {
        $orderNo = date('YmdHis') . strtoupper(substr(md5(uniqid()), 0, 8));

        // Create payment record
        $payment = Payment::create([
            'user_id' => $userId,
            'type' => 'recharge',
            'amount' => $amount,
            'payment_method' => 'epay',
            'status' => 'pending',
        ]);

        $params = [
            'pid' => (int) $this->merchantId,
            'type' => $this->mapPaymentType($paymentMethod),
            'out_trade_no' => $orderNo,
            'notify_url' => $this->notifyUrl,
            'return_url' => $this->returnUrl,
            'name' => '账户充值',
            'money' => number_format($amount, 2, '.', ''),
            'clientip' => request()->ip() ?? '127.0.0.1',
            'param' => $payment->id,
        ];

        $params['sign'] = $this->generateSign($params);
        $params['sign_type'] = 'MD5';

        return [
            'success' => true,
            'pay_url' => $this->apiUrl . '/submit.php',
            'params' => $params,
            'payment_id' => $payment->id,
            'order_no' => $orderNo,
        ];
    }

    /**
     * Query payment status from Epay.
     */
    public function queryOrder(string $orderNo): array
    {
        $params = [
            'act' => 'order',
            'pid' => $this->merchantId,
            'key' => $this->merchantKey,
            'out_trade_no' => $orderNo,
        ];

        try {
            $queryUrl = $this->apiUrl . '/api.php';
            $response = Http::asForm()->timeout(15)->post($queryUrl, $params);

            if (!$response->successful()) {
                Log::error('Epay query failed', ['order_no' => $orderNo, 'response' => $response->body()]);
                return ['success' => false, 'error' => 'API request failed'];
            }

            $result = $this->parseResponse($response->body());

            Log::info('Epay query result', ['order_no' => $orderNo, 'result' => $result]);

            return [
                'success' => true,
                'status' => $result['status'] ?? 'unknown',
                'money' => $result['money'] ?? 0,
                'trade_no' => $result['trade_no'] ?? null,
            ];

        } catch (\Exception $e) {
            Log::error('Epay query exception', ['order_no' => $orderNo, 'error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Verify callback signature from Epay notify/return.
     */
    public function verifyNotify(array $data): bool
    {
        if (empty($data['sign'])) {
            Log::warning('Epay notify missing signature');
            return false;
        }

        $receivedSign = $data['sign'];
        $expectedSign = $this->generateSign($data);

        return strtolower($receivedSign) === strtolower($expectedSign);
    }

    /**
     * Handle payment callback/notify from Epay.
     */
    public function handleNotify(array $data): array
    {
        // Verify signature
        if (!$this->verifyNotify($data)) {
            Log::error('Epay notify signature verification failed', ['data' => $data]);
            return ['success' => false, 'message' => '签名验证失败'];
        }

        $orderNo = $data['out_trade_no'] ?? '';
        $tradeStatus = $data['trade_status'] ?? '';
        $tradeNo = $data['trade_no'] ?? '';

        // Only process successful payments
        if ($tradeStatus !== 'TRADE_SUCCESS') {
            Log::info('Epay notify - trade not success', ['order_no' => $orderNo, 'status' => $tradeStatus]);
            return ['success' => true, 'message' => '非支付成功状态'];
        }

        // Find order by order number
        $order = Order::where('order_no', $orderNo)->first();

        if ($order) {
            // This is an order payment (VM purchase)
            if ($order->payment_status === 'paid') {
                return ['success' => true, 'message' => '订单已处理'];
            }

            \DB::beginTransaction();
            try {
                $order->update([
                    'payment_status' => 'paid',
                    'transaction_id' => $tradeNo,
                    'paid_at' => now(),
                ]);

                // Create payment record
                Payment::create([
                    'user_id' => $order->user_id,
                    'order_id' => $order->id,
                    'type' => 'order',
                    'amount' => $order->amount,
                    'payment_method' => 'epay',
                    'transaction_id' => $tradeNo,
                    'status' => 'paid',
                    'paid_at' => now(),
                ]);

                \DB::commit();

                Log::info('Order payment completed via Epay', [
                    'order_no' => $orderNo,
                    'trade_no' => $tradeNo,
                ]);

            } catch (\Exception $e) {
                \DB::rollBack();
                Log::error('Failed to process Epay order payment', [
                    'order_no' => $orderNo,
                    'error' => $e->getMessage(),
                ]);
                return ['success' => false, 'message' => '处理失败'];
            }
        } else {
            // Check if it's a recharge payment
            $payment = Payment::where('type', 'recharge')
                ->where('status', 'pending')
                ->where('amount', $data['money'] ?? 0)
                ->latest()
                ->first();

            if ($payment && $payment->status !== 'paid') {
                \DB::beginTransaction();
                try {
                    $payment->markAsPaid($tradeNo);

                    // Add balance to user
                    $user = $payment->user;
                    $user->increment('balance', $payment->amount);

                    \DB::commit();

                    Log::info('Recharge payment completed via Epay', [
                        'payment_id' => $payment->id,
                        'user_id' => $payment->user_id,
                        'amount' => $payment->amount,
                        'trade_no' => $tradeNo,
                    ]);

                } catch (\Exception $e) {
                    \DB::rollBack();
                    Log::error('Failed to process Epay recharge', [
                        'error' => $e->getMessage(),
                    ]);
                    return ['success' => false, 'message' => '处理失败'];
                }
            }
        }

        return ['success' => true, 'message' => 'success'];
    }

    /**
     * Process refund via Epay.
     */
    public function refund(string $orderNo, ?float $amount = null): array
    {
        $order = Order::where('order_no', $orderNo)->first();
        if (!$order) {
            return ['success' => false, 'message' => '订单不存在'];
        }

        if ($order->payment_status !== 'paid') {
            return ['success' => false, 'message' => '订单未支付'];
        }

        $params = [
            'pid' => $this->merchantId,
            'trade_no' => $order->transaction_id,
            'out_trade_no' => $orderNo,
            'money' => number_format($amount ?? $order->amount, 2, '.', ''),
        ];

        $params['sign'] = $this->generateSign($params);
        $params['sign_type'] = 'MD5';

        try {
            $response = Http::asForm()
                ->timeout(15)
                ->post($this->apiUrl . '/api.php?act=refund', $params);

            Log::info('Epay refund request', [
                'order_no' => $orderNo,
                'params' => $params,
                'response' => $response->body(),
            ]);

            $result = $this->parseResponse($response->body());

            if (($result['code'] ?? 0) == 1) {
                $order->update(['payment_status' => 'refunded']);
                return ['success' => true, 'message' => '退款成功'];
            }

            return ['success' => false, 'message' => $result['msg'] ?? '退款失败'];

        } catch (\Exception $e) {
            Log::error('Epay refund exception', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Generate MD5 signature for Epay API.
     * Epay sign: Sort params by key, concatenate with '&', append merchant key, MD5.
     */
    public function generateSign(array $params): string
    {
        // Remove sign and sign_type if present
        unset($params['sign'], $params['sign_type']);

        // Sort by key
        ksort($params);

        // Build query string
        $signStr = '';
        foreach ($params as $key => $value) {
            if ($value === '' || $value === null) {
                continue;
            }
            $signStr .= "{$key}={$value}&";
        }
        $signStr = rtrim($signStr, '&');

        // Append merchant key
        $signStr .= $this->merchantKey;

        return md5($signStr);
    }

    /**
     * Verify signature convenience method.
     */
    public function verifySign(array $data): bool
    {
        return $this->verifyNotify($data);
    }

    /**
     * Check if Epay is fully configured.
     */
    public function isConfigured(): bool
    {
        return !empty($this->apiUrl) && !empty($this->merchantId) && !empty($this->merchantKey);
    }

    /**
     * Test Epay API connection by querying order status.
     */
    public function testConnection(): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'message' => 'Epay not configured'];
        }

        try {
            $params = [
                'act' => 'order',
                'pid' => $this->merchantId,
                'key' => $this->merchantKey,
                'out_trade_no' => 'CONNECTION_TEST_' . time(),
            ];

            $queryUrl = $this->apiUrl . '/api.php';
            $response = \Illuminate\Support\Facades\Http::asForm()
                ->timeout(15)
                ->post($queryUrl, $params);

            $result = $this->parseResponse($response->body());

            return [
                'success' => true,
                'message' => 'Epay API connection successful',
                'api_url' => $this->apiUrl,
                'merchant_id' => $this->merchantId,
                'response' => $result,
            ];
        } catch (\Exception $e) {
            \Log::error('Epay testConnection failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => 'Connection failed: ' . $e->getMessage()];
        }
    }

    /**
     * Map internal payment method to Epay type string.
     */
    private function mapPaymentType(string $method): string
    {
        return match ($method) {
            'alipay' => 'alipay',
            'wechat' => 'wxpay',
            'qqpay' => 'qqpay',
            'bank' => 'bank',
            default => 'alipay',
        };
    }

    /**
     * Parse Epay response (various formats: querystring, JSON, plain text).
     */
    private function parseResponse(string $response): array
    {
        // Try JSON first
        $data = json_decode($response, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
            return $data;
        }

        // Try URL-encoded
        $parsed = [];
        parse_str($response, $parsed);
        if (!empty($parsed)) {
            return $parsed;
        }

        // Plain text like "success" or "1|msg"
        if (str_contains($response, '|')) {
            $parts = explode('|', $response);
            return [
                'code' => $parts[0] ?? '',
                'msg' => $parts[1] ?? '',
            ];
        }

        return ['raw' => $response];
    }
}
