<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class VmRenewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'billing_cycle' => ['required', 'string', 'in:monthly,quarterly,yearly'],
            'coupon_code'   => ['nullable', 'string', 'max:50'],
        ];
    }
}
