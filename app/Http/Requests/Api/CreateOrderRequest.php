<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class CreateOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id'    => ['required', 'integer', 'exists:products,id'],
            'billing_cycle' => ['required', 'string', 'in:monthly,quarterly,yearly'],
            'coupon_code'   => ['nullable', 'string', 'max:50'],
        ];
    }
}
