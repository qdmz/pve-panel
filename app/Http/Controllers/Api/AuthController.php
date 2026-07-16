<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ForgotPasswordRequest;
use App\Http\Requests\Api\LoginRequest;
use App\Http\Requests\Api\RegisterRequest;
use App\Http\Requests\Api\ResetPasswordRequest;
use App\Jobs\SendEmailJob;
use App\Models\LoginLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function register(RegisterRequest $request)
    {
        try {
            $user = User::create([
                'name'     => $request->name,
                'email'    => $request->email,
                'password' => Hash::make($request->password),
            ]);

            $token = Str::random(64);
            \DB::table('email_verifications')->insert([
                'user_id'    => $user->id,
                'token'      => $token,
                'created_at' => now(),
            ]);

            SendEmailJob::dispatch($user->email, 'verification', [
                'token' => $token,
                'user'  => $user,
            ]);

            return ApiResponse::success(
                ['user' => $user->makeHidden(['password'])],
                'Registration successful. Please check your email to verify your account.',
                201
            );
        } catch (\Exception $e) {
            \Log::error('AuthController::register failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Registration failed. Please try again.', 500);
        }
    }

    public function login(LoginRequest $request)
    {
        try {
            $user = User::where('email', $request->email)->first();

            if (!$user || !Hash::check($request->password, $user->password)) {
                return ApiResponse::error('Invalid email or password.', 401);
            }

            if (is_null($user->email_verified_at)) {
                return ApiResponse::error('Please verify your email address before logging in.', 403);
            }

            if ($user->status === 'disabled') {
                return ApiResponse::error('Your account has been disabled. Please contact support.', 403);
            }

            $token = $user->createToken('api-token')->plainTextToken;

            $user->update([
                'last_login_at' => now(),
                'last_login_ip' => $request->ip(),
            ]);

            LoginLog::create([
                'user_id'    => $user->id,
                'ip'         => $request->ip(),
                'user_agent' => $request->userAgent(),
                'status'     => 'success',
            ]);

            return ApiResponse::success([
                'token' => $token,
                'user'  => $user,
            ], 'Login successful.');
        } catch (\Exception $e) {
            \Log::error('AuthController::login failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Login failed. Please try again.', 500);
        }
    }

    public function logout(Request $request)
    {
        try {
            $request->user()->currentAccessToken()->delete();
            return ApiResponse::success(null, 'Logged out successfully.');
        } catch (\Exception $e) {
            \Log::error('AuthController::logout failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Logout failed.', 500);
        }
    }

    public function forgotPassword(ForgotPasswordRequest $request)
    {
        try {
            $status = Password::sendResetLink($request->only('email'));

            if ($status === Password::RESET_LINK_SENT) {
                return ApiResponse::success(null, 'Password reset link sent to your email.');
            }

            return ApiResponse::error('Unable to send reset link.', 500);
        } catch (\Exception $e) {
            \Log::error('AuthController::forgotPassword failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to send reset link.', 500);
        }
    }

    public function resetPassword(ResetPasswordRequest $request)
    {
        try {
            $status = Password::reset(
                $request->only('email', 'password', 'password_confirmation', 'token'),
                function ($user, $password) {
                    $user->password = Hash::make($password);
                    $user->save();
                }
            );

            if ($status === Password::PASSWORD_RESET) {
                return ApiResponse::success(null, 'Password reset successfully.');
            }

            return ApiResponse::error('Invalid or expired reset token.', 400);
        } catch (\Exception $e) {
            \Log::error('AuthController::resetPassword failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to reset password.', 500);
        }
    }

    public function verifyEmail(string $token)
    {
        try {
            $record = \DB::table('email_verifications')->where('token', $token)->first();

            if (!$record) {
                return ApiResponse::error('Invalid or expired verification token.', 400);
            }

            $user = User::find($record->user_id);

            if (!$user) {
                return ApiResponse::error('User not found.', 404);
            }

            $user->update(['email_verified_at' => now()]);

            \DB::table('email_verifications')->where('token', $token)->delete();

            SendEmailJob::dispatch($user->email, 'welcome', ['name' => $user->name]);

            return ApiResponse::success(null, 'Email verified successfully.');
        } catch (\Exception $e) {
            \Log::error('AuthController::verifyEmail failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to verify email.', 500);
        }
    }

    public function resendVerification(Request $request)
    {
        try {
            $user = $request->user();

            if ($user->hasVerifiedEmail()) {
                return ApiResponse::error('Email is already verified.', 400);
            }

            \DB::table('email_verifications')->where('user_id', $user->id)->delete();

            $token = Str::random(64);
            \DB::table('email_verifications')->insert([
                'user_id'    => $user->id,
                'token'      => $token,
                'created_at' => now(),
            ]);

            SendEmailJob::dispatch($user->email, 'verification', [
                'token' => $token,
                'user'  => $user,
            ]);

            return ApiResponse::success(null, 'Verification email resent.');
        } catch (\Exception $e) {
            \Log::error('AuthController::resendVerification failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to resend verification email.', 500);
        }
    }

    public function me(Request $request)
    {
        return ApiResponse::success(['user' => $request->user()]);
    }
}
