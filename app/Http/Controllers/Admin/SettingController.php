<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\EmailService;
use App\Services\EpayService;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    protected array $allowedGroups = ['general', 'payment', 'email', 'verify', 'site'];

    public function show(string $group)
    {
        try {
            if (!in_array($group, $this->allowedGroups)) {
                return ApiResponse::error('Invalid settings group.', 400);
            }

            $settings = Setting::getByGroup($group);

            return ApiResponse::success(['settings' => $settings]);
        } catch (\Exception $e) {
            \Log::error('Admin\\SettingController::show failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to retrieve settings.', 500);
        }
    }

    public function update(Request $request, string $group)
    {
        try {
            if (!in_array($group, $this->allowedGroups)) {
                return ApiResponse::error('Invalid settings group.', 400);
            }

            $settings = $request->all();

            foreach ($settings as $key => $value) {
                Setting::updateOrCreate(
                    ['group' => $group, 'key' => $key],
                    ['value' => is_array($value) ? json_encode($value) : $value]
                );
            }

            return ApiResponse::success(
                ['settings' => Setting::getByGroup($group)],
                'Settings updated.'
            );
        } catch (\Exception $e) {
            \Log::error('Admin\\SettingController::update failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to update settings.', 500);
        }
    }

    public function testSmtp(Request $request)
    {
        try {
            $request->validate([
                'email' => ['required', 'email'],
            ]);

            $emailService = new EmailService();
            $result = $emailService->sendTestEmail($request->email);

            if ($result) {
                return ApiResponse::success(null, 'Test email sent successfully.');
            }

            return ApiResponse::error('Failed to send test email. Check SMTP configuration.', 500);
        } catch (\Exception $e) {
            \Log::error('Admin\\SettingController::testSmtp failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('SMTP test failed: ' . $e->getMessage(), 500);
        }
    }

    public function testEpay()
    {
        try {
            $epay = new EpayService();

            if (!$epay->isConfigured()) {
                return ApiResponse::error('Epay is not configured. Please set EPAY_API_URL, EPAY_PID, and EPAY_KEY.', 400);
            }

            $result = $epay->testConnection();

            if ($result['success']) {
                return ApiResponse::success($result, 'Epay connection successful.');
            }

            return ApiResponse::error($result['message'], 400);
        } catch (\Exception $e) {
            \Log::error('Admin\\SettingController::testEpay failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Epay test failed: ' . $e->getMessage(), 500);
        }
    }

    public function emailTemplates()
    {
        try {
            $templates = [
                [
                    'id'      => 1,
                    'name'    => 'verification',
                    'title'   => 'Email Verification',
                    'subject' => 'Please verify your email address',
                ],
                [
                    'id'      => 2,
                    'name'    => 'password-reset',
                    'title'   => 'Password Reset',
                    'subject' => 'Reset your password',
                ],
                [
                    'id'      => 3,
                    'name'    => 'welcome',
                    'title'   => 'Welcome Email',
                    'subject' => 'Welcome to our platform!',
                ],
                [
                    'id'      => 4,
                    'name'    => 'vm-expiry',
                    'title'   => 'VM Expiry Notice',
                    'subject' => 'Your VM is expiring soon',
                ],
                [
                    'id'      => 5,
                    'name'    => 'test',
                    'title'   => 'Test Email',
                    'subject' => 'SMTP Test Email',
                ],
            ];

            // Load saved templates
            foreach ($templates as &$template) {
                $saved = Setting::getByGroup('email_templates');
                if (isset($saved[$template['name']])) {
                    $template = array_merge($template, json_decode($saved[$template['name']], true));
                }
                $template['preview'] = $this->getPreview($template['name']);
            }

            return ApiResponse::success(['templates' => $templates]);
        } catch (\Exception $e) {
            \Log::error('Admin\\SettingController::emailTemplates failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to retrieve email templates.', 500);
        }
    }

    public function updateEmailTemplate(Request $request, $templateId)
    {
        try {
            $templateName = match ((int) $templateId) {
                1 => 'verification',
                2 => 'password-reset',
                3 => 'welcome',
                4 => 'vm-expiry',
                5 => 'test',
                default => null,
            };

            if (!$templateName) {
                return ApiResponse::error('Template not found.', 404);
            }

            $data = $request->only(['subject', 'content']);

            Setting::updateOrCreate(
                ['group' => 'email_templates', 'key' => $templateName],
                ['value' => json_encode($data)]
            );

            return ApiResponse::success(['template' => $data], 'Email template updated.');
        } catch (\Exception $e) {
            \Log::error('Admin\\SettingController::updateEmailTemplate failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to update email template.', 500);
        }
    }

    public function previewTemplate($templateId)
    {
        try {
            $templateName = match ((int) $templateId) {
                1 => 'verification',
                2 => 'password-reset',
                3 => 'welcome',
                4 => 'vm-expiry',
                5 => 'test',
                default => null,
            };

            if (!$templateName) {
                return ApiResponse::error('Template not found.', 404);
            }

            return ApiResponse::success(['preview' => $this->getPreview($templateName)]);
        } catch (\Exception $e) {
            \Log::error('Admin\\SettingController::previewTemplate failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to preview template.', 500);
        }
    }

    protected function getPreview(string $name): string
    {
        $previews = [
            'verification'   => 'Click here to verify your email: {{verify_url}}',
            'password-reset' => 'Click here to reset your password: {{reset_url}}',
            'welcome'        => 'Welcome {{name}}, thank you for registering!',
            'vm-expiry'       => 'Your VM {{vm_name}} will expire in {{days_left}} days.',
            'test'           => 'This is a test email from your SMTP configuration.',
        ];

        return $previews[$name] ?? '';
    }
}
