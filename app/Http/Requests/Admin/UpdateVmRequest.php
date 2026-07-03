<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateVmRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'traffic_limit' => ['sometimes', 'integer', 'min:0'],
            'notes'         => ['nullable', 'string'],
            'expires_at'    => ['nullable', 'date'],
        ];
    }
}
