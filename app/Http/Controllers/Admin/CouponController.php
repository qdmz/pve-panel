<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BatchCouponRequest;
use App\Http\Requests\Admin\CreateCouponRequest;
use App\Models\Coupon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CouponController extends Controller
{
    public function index(Request $request)
    {
        try {
            $coupons = Coupon::when($request->type, function ($query, $type) {
                return $query->where('type', $type);
            })
            ->when($request->status, function ($query, $status) {
                return $query->where('status', $status);
            })
            ->orderBy('created_at', 'desc')
            ->paginate(20);

            return ApiResponse::paginated($coupons, 'Coupons retrieved.');
        } catch (\Exception $e) {
            \Log::error('Admin\\CouponController::index failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to retrieve coupons.', 500);
        }
    }

    public function store(CreateCouponRequest $request)
    {
        try {
            $coupon = Coupon::create($request->validated());

            return ApiResponse::success(['coupon' => $coupon], 'Coupon created.', 201);
        } catch (\Exception $e) {
            \Log::error('Admin\\CouponController::store failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to create coupon.', 500);
        }
    }

    public function update(Request $request, Coupon $coupon)
    {
        try {
            $data = $request->only([
                'code', 'type', 'value', 'min_amount', 'max_uses',
                'per_user_limit', 'starts_at', 'expires_at', 'status', 'description',
            ]);

            $coupon->update($data);

            return ApiResponse::success(['coupon' => $coupon], 'Coupon updated.');
        } catch (\Exception $e) {
            \Log::error('Admin\\CouponController::update failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to update coupon.', 500);
        }
    }

    public function destroy(Coupon $coupon)
    {
        try {
            $coupon->delete();

            return ApiResponse::success(null, 'Coupon deleted.');
        } catch (\Exception $e) {
            \Log::error('Admin\\CouponController::destroy failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to delete coupon.', 500);
        }
    }

    public function toggleStatus(Coupon $coupon)
    {
        try {
            $coupon->status = $coupon->status === 'active' ? 'inactive' : 'active';
            $coupon->save();

            return ApiResponse::success(['coupon' => $coupon], 'Coupon status toggled.');
        } catch (\Exception $e) {
            \Log::error('Admin\\CouponController::toggleStatus failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to toggle coupon status.', 500);
        }
    }

    public function batch(BatchCouponRequest $request)
    {
        try {
            $quantity   = $request->quantity;
            $prefix     = $request->prefix ?? '';
            $coupons    = [];

            for ($i = 0; $i < $quantity; $i++) {
                $code = strtoupper($prefix . Str::random(8));

                $coupon = Coupon::create([
                    'code'           => $code,
                    'type'           => $request->type,
                    'value'          => $request->value,
                    'min_amount'     => $request->min_amount,
                    'max_uses'       => $request->max_uses,
                    'expires_at'     => $request->expires_at,
                    'description'    => $request->description ?? 'Batch generated coupon',
                    'status'         => 'active',
                ]);

                $coupons[] = $coupon;
            }

            return ApiResponse::success([
                'count'   => count($coupons),
                'coupons' => $coupons,
            ], "{$quantity} coupons generated.", 201);
        } catch (\Exception $e) {
            \Log::error('Admin\\CouponController::batch failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to generate coupons.', 500);
        }
    }
}
