<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class BatchCouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'quantity'     => ['required', 'integer', 'min:1', 'max:500'],
            'prefix'       => ['nullable', 'string', 'max:10'],
            'type'         => ['required', 'string', 'in:fixed,percentage'],
            'value'        => ['required', 'numeric', 'min:0'],
            'min_amount'   => ['nullable', 'numeric', 'min:0'],
            'max_uses'     => ['nullable', 'integer', 'min:1'],
            'expires_at'   => ['nullable', 'date'],
            'description'  => ['nullable', 'string'],
        ];
    }
}
