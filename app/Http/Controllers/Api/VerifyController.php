<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SubmitVerificationRequest;
use Illuminate\Http\Request;

class VerifyController extends Controller
{
    public function show(Request $request)
    {
        try {
            $verification = $request->user()->verification;

            return ApiResponse::success(['verification' => $verification]);
        } catch (\Exception $e) {
            \Log::error('VerifyController::show failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to retrieve verification status.', 500);
        }
    }

    public function store(SubmitVerificationRequest $request)
    {
        try {
            $user = $request->user();

            if ($user->verification && $user->verification->status === 'pending') {
                return ApiResponse::error('You already have a pending verification request.', 400);
            }

            if ($user->verification && $user->verification->status === 'approved') {
                return ApiResponse::error('Your verification is already approved.', 400);
            }

            $verification = $user->verification()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'real_name'      => $request->real_name,
                    'id_type'        => $request->id_type,
                    'id_number'      => $request->id_number,
                    'id_front_photo' => $request->id_front_photo,
                    'id_back_photo'  => $request->id_back_photo,
                    'status'         => 'pending',
                ]
            );

            return ApiResponse::success(
                ['verification' => $verification],
                'Verification submitted for review.',
                201
            );
        } catch (\Exception $e) {
            \Log::error('VerifyController::store failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to submit verification.', 500);
        }
    }

    public function update(SubmitVerificationRequest $request)
    {
        try {
            $user = $request->user();
            $verification = $user->verification;

            if (!$verification || $verification->status !== 'rejected') {
                return ApiResponse::error('No rejected verification to resubmit.', 400);
            }

            $verification->update([
                'real_name'      => $request->real_name,
                'id_type'        => $request->id_type,
                'id_number'      => $request->id_number,
                'id_front_photo' => $request->id_front_photo,
                'id_back_photo'  => $request->id_back_photo,
                'status'         => 'pending',
                'admin_note'     => null,
            ]);

            return ApiResponse::success(['verification' => $verification], 'Verification resubmitted.');
        } catch (\Exception $e) {
            \Log::error('VerifyController::update failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to resubmit verification.', 500);
        }
    }
}
