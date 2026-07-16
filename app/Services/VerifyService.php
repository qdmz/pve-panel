<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\Verification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class VerifyService
{
    private string $provider;

    public function __construct()
    {
        $this->provider = Setting::getValue('verify_provider', 'aliyun');
    }

    /**
     * Auto-verify based on configured provider.
     */
    public function autoVerify(Verification $verification): array
    {
        switch ($this->provider) {
            case 'tencent':
                return $this->verifyWithTencent($verification->real_name, $verification->id_number);
            case 'baidu':
                return $this->verifyWithBaidu($verification->real_name, $verification->id_number);
            case 'aliyun':
            default:
                return $this->verifyWithAliyun($verification->real_name, $verification->id_number);
        }
    }

    /**
     * Aliyun (Cloud Market) real-name verification.
     * API: https://market.aliyun.com/products/57000002/cmapi00037166.html
     */
    public function verifyWithAliyun(string $realName, string $idNumber): array
    {
        $appCode = Setting::getValue('aliyun_verify_app_code', '');

        if (empty($appCode)) {
            Log::warning('Aliyun verify AppCode not configured');
            return ['success' => false, 'message' => '验证服务未配置', 'code' => -1];
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'APPCODE ' . $appCode,
                'Content-Type' => 'application/x-www-form-urlencoded; charset=UTF-8',
            ])->asForm()->post('https://idcert.market.alicloudapi.com/idcard', [
                'idCard' => $idNumber,
                'name' => $realName,
            ]);

            if (!$response->successful()) {
                Log::error('Aliyun verify HTTP error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return ['success' => false, 'message' => '验证服务请求失败', 'code' => -2];
            }

            $result = $response->json();

            Log::info('Aliyun verify result', [
                'name' => $this->maskRealName($realName),
                'id_card' => $this->maskIdNumber($idNumber),
                'result' => $result,
            ]);

            // Aliyun returns: {"status": "01", "msg": "实名认证通过"}
            // status "01" means match
            $isMatch = ($result['status'] ?? '') === '01';

            return [
                'success' => $isMatch,
                'message' => $isMatch ? '实名验证通过' : ($result['msg'] ?? '验证失败'),
                'provider' => 'aliyun',
                'raw' => $result,
            ];

        } catch (\Exception $e) {
            Log::error('Aliyun verify exception: ' . $e->getMessage());
            return ['success' => false, 'message' => '验证服务异常', 'code' => -3];
        }
    }

    /**
     * Tencent Cloud real-name verification.
     * API: https://cloud.tencent.com/document/product/1031/33354
     */
    public function verifyWithTencent(string $realName, string $idNumber): array
    {
        $secretId = Setting::getValue('tencent_secret_id', '');
        $secretKey = Setting::getValue('tencent_secret_key', '');

        if (empty($secretId) || empty($secretKey)) {
            Log::warning('Tencent verify credentials not configured');
            return ['success' => false, 'message' => '验证服务未配置', 'code' => -1];
        }

        try {
            $timestamp = time();
            $nonce = (string) mt_rand(100000, 999999);
            $action = 'IdCardVerification';
            $version = '2020-11-05';
            $region = 'ap-guangzhou';
            $service = 'faceid';

            $params = [
                'Action' => $action,
                'Version' => $version,
                'Region' => $region,
                'IdCard' => $idNumber,
                'Name' => $realName,
                'Timestamp' => $timestamp,
                'Nonce' => $nonce,
                'SecretId' => $secretId,
            ];

            ksort($params);

            $signStr = 'POST' . $service . '.tencentcloudapi.com/?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
            $signature = base64_encode(hash_hmac('sha256', $signStr, $secretKey, true));

            $headers = [
                'Content-Type' => 'application/json',
                'X-TC-Action' => $action,
                'X-TC-Version' => $version,
                'X-TC-Timestamp' => $timestamp,
                'X-TC-Region' => $region,
                'Authorization' => "TC3-HMAC-SHA256 Credential={$secretId}/{$service}/tc3_request, SignedHeaders=content-type, Signature={$signature}",
            ];

            $response = Http::withHeaders($headers)
                ->timeout(15)
                ->post("https://{$service}.tencentcloudapi.com", json_encode($params));

            if (!$response->successful()) {
                Log::error('Tencent verify HTTP error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return ['success' => false, 'message' => '验证服务请求失败', 'code' => -2];
            }

            $result = $response->json('Response', []);

            Log::info('Tencent verify result', [
                'name' => $this->maskRealName($realName),
                'id_card' => $this->maskIdNumber($idNumber),
                'result' => $result,
            ]);

            $isMatch = ($result['Result'] ?? '') === '0';

            return [
                'success' => $isMatch,
                'message' => $isMatch ? '实名验证通过' : ($result['Description'] ?? '验证失败'),
                'provider' => 'tencent',
                'raw' => $result,
            ];

        } catch (\Exception $e) {
            Log::error('Tencent verify exception: ' . $e->getMessage());
            return ['success' => false, 'message' => '验证服务异常', 'code' => -3];
        }
    }

    /**
     * Baidu AI real-name verification.
     * API: https://ai.baidu.com/tech/facecompare
     * Note: Baidu uses face comparison, not direct ID verification.
     * This implementation checks if the ID number format is valid.
     */
    public function verifyWithBaidu(string $realName, string $idNumber): array
    {
        $apiKey = Setting::getValue('baidu_api_key', '');
        $secretKey = Setting::getValue('baidu_secret_key', '');

        if (empty($apiKey) || empty($secretKey)) {
            Log::warning('Baidu verify credentials not configured');
            return ['success' => false, 'message' => '验证服务未配置', 'code' => -1];
        }

        try {
            // Get access token
            $tokenResponse = Http::asForm()->post('https://aip.baidubce.com/oauth/2.0/token', [
                'grant_type' => 'client_credentials',
                'client_id' => $apiKey,
                'client_secret' => $secretKey,
            ]);

            if (!$tokenResponse->successful()) {
                Log::error('Baidu token fetch failed', ['response' => $tokenResponse->body()]);
                return ['success' => false, 'message' => '获取百度Token失败', 'code' => -2];
            }

            $accessToken = $tokenResponse->json('access_token');

            if (!$accessToken) {
                return ['success' => false, 'message' => '百度Token为空', 'code' => -2];
            }

            // Baidu ID Card Verification
            $response = Http::withHeaders([
                'Content-Type' => 'application/x-www-form-urlencoded',
            ])->asForm()->post(
                "https://aip.baidubce.com/rest/2.0/face/v3/person/idmatch?access_token={$accessToken}",
                [
                    'id_card_number' => $idNumber,
                    'name' => $realName,
                ]
            );

            if (!$response->successful()) {
                Log::error('Baidu verify HTTP error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return ['success' => false, 'message' => '验证服务请求失败', 'code' => -3];
            }

            $result = $response->json();

            Log::info('Baidu verify result', [
                'name' => $this->maskRealName($realName),
                'id_card' => $this->maskIdNumber($idNumber),
                'error_code' => $result['error_code'] ?? 0,
            ]);

            // Baidu returns error_code=0 for success
            $isMatch = ($result['error_code'] ?? 1) === 0;

            return [
                'success' => $isMatch,
                'message' => $isMatch ? '实名验证通过' : ($result['error_msg'] ?? '验证失败'),
                'provider' => 'baidu',
                'raw' => $result,
            ];

        } catch (\Exception $e) {
            Log::error('Baidu verify exception: ' . $e->getMessage());
            return ['success' => false, 'message' => '验证服务异常', 'code' => -4];
        }
    }

    /**
     * Mask real name for logging (show last character only).
     */
    private function maskRealName(string $name): string
    {
        if (mb_strlen($name) <= 1) {
            return '*';
        }
        return str_repeat('*', max(0, mb_strlen($name) - 1)) . mb_substr($name, -1);
    }

    /**
     * Mask ID number for logging (show first 3 and last 4 digits).
     */
    private function maskIdNumber(string $idNumber): string
    {
        if (strlen($idNumber) <= 7) {
            return str_repeat('*', strlen($idNumber));
        }
        return substr($idNumber, 0, 3) . str_repeat('*', strlen($idNumber) - 7) . substr($idNumber, -4);
    }
}
