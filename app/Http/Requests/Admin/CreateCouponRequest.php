<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class CreateCouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code'           => ['required', 'string', 'max:50', 'unique:coupons,code'],
            'type'           => ['required', 'string', 'in:fixed,percentage'],
            'value'          => ['required', 'numeric', 'min:0'],
            'min_amount'     => ['nullable', 'numeric', 'min:0'],
            'max_uses'       => ['nullable', 'integer', 'min:1'],
            'per_user_limit' => ['nullable', 'integer', 'min:1'],
            'starts_at'      => ['nullable', 'date'],
            'expires_at'     => ['nullable', 'date', 'after:starts_at'],
            'status'         => ['sometimes', 'string', 'in:active,inactive'],
            'description'    => ['nullable', 'string'],
        ];
    }
}
