<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RejectVerificationRequest;
use App\Models\Verification;
use Illuminate\Http\Request;

class VerifyController extends Controller
{
    public function index(Request $request)
    {
        try {
            $verifications = Verification::with('user:id,name,email')
                                   ->when($request->status, function ($query, $status) {
                                       return $query->where('status', $status);
                                   })
                                   ->orderBy('updated_at', 'desc')
                                   ->paginate(20);

            return ApiResponse::paginated($verifications, 'Verifications retrieved.');
        } catch (\Exception $e) {
            \Log::error('Admin\\VerifyController::index failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to retrieve verifications.', 500);
        }
    }

    public function show(Verification $verification)
    {
        try {
            $verification->load('user:id,name,email');

            return ApiResponse::success(['verification' => $verification]);
        } catch (\Exception $e) {
            \Log::error('Admin\\VerifyController::show failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to retrieve verification.', 500);
        }
    }

    public function approve(Verification $verification)
    {
        try {
            if ($verification->status !== 'pending') {
                return ApiResponse::error('This verification is not pending.', 400);
            }

            $verification->update([
                'status'      => 'approved',
                'verified_at' => now(),
                'admin_note'  => null,
            ]);

            return ApiResponse::success(['verification' => $verification], 'Verification approved.');
        } catch (\Exception $e) {
            \Log::error('Admin\\VerifyController::approve failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to approve verification.', 500);
        }
    }

    public function reject(RejectVerificationRequest $request, Verification $verification)
    {
        try {
            if ($verification->status !== 'pending') {
                return ApiResponse::error('This verification is not pending.', 400);
            }

            $verification->update([
                'status'     => 'rejected',
                'admin_note' => $request->note,
            ]);

            return ApiResponse::success(['verification' => $verification], 'Verification rejected.');
        } catch (\Exception $e) {
            \Log::error('Admin\\VerifyController::reject failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to reject verification.', 500);
        }
    }
}
