<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'    => ['sometimes', 'string', 'max:255'],
            'balance' => ['sometimes', 'numeric', 'min:0'],
            'status'  => ['sometimes', 'string', 'in:active,disabled'],
            'role'    => ['sometimes', 'string', 'in:user,admin'],
            'notes'   => ['nullable', 'string'],
        ];
    }
}
