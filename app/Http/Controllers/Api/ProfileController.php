<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ChangePasswordRequest;
use App\Http\Requests\Api\UpdateProfileRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    public function update(UpdateProfileRequest $request)
    {
        try {
            $user = $request->user();
            $user->update($request->only(['name', 'phone', 'avatar']));

            return ApiResponse::success(['user' => $user], 'Profile updated successfully.');
        } catch (\Exception $e) {
            \Log::error('ProfileController::update failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to update profile.', 500);
        }
    }

    public function changePassword(ChangePasswordRequest $request)
    {
        try {
            $user = $request->user();

            if (!Hash::check($request->current_password, $user->password)) {
                return ApiResponse::error('Current password is incorrect.', 400);
            }

            $user->update(['password' => Hash::make($request->new_password)]);

            // Revoke all tokens except current
            $user->tokens()->where('id', '!=', $user->currentAccessToken()->id)->delete();

            return ApiResponse::success(null, 'Password changed successfully.');
        } catch (\Exception $e) {
            \Log::error('ProfileController::changePassword failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to change password.', 500);
        }
    }

    public function loginLogs(Request $request)
    {
        try {
            $logs = $request->user()->loginLogs()
                         ->orderBy('login_at', 'desc')
                         ->paginate(20);

            return ApiResponse::paginated($logs, 'Login logs retrieved.');
        } catch (\Exception $e) {
            \Log::error('ProfileController::loginLogs failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to retrieve login logs.', 500);
        }
    }

    public function notifications(Request $request)
    {
        try {
            $user = $request->user();
            $user->update([
                'notification_preferences' => $request->only([
                    'email_expiry', 'email_payment', 'email_news',
                ]),
            ]);

            return ApiResponse::success(
                ['preferences' => $user->notification_preferences],
                'Notification preferences updated.'
            );
        } catch (\Exception $e) {
            \Log::error('ProfileController::notifications failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to update preferences.', 500);
        }
    }

    public function createApiKey(Request $request)
    {
        try {
            $token = $request->user()->createToken('api-key-' . now()->timestamp)->plainTextToken;

            return ApiResponse::success(['api_key' => $token], 'API key generated.');
        } catch (\Exception $e) {
            \Log::error('ProfileController::createApiKey failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to generate API key.', 500);
        }
    }

    public function revokeApiKey(Request $request)
    {
        try {
            $request->user()->tokens()
                            ->where('name', 'like', 'api-key-%')
                            ->delete();

            return ApiResponse::success(null, 'API key revoked.');
        } catch (\Exception $e) {
            \Log::error('ProfileController::revokeApiKey failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to revoke API key.', 500);
        }
    }
}
