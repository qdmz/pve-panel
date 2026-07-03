<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class VmReinstallRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'template_id'    => ['required', 'string'],
            'confirmation'   => ['required', 'string', 'in:REINSTALL'],
        ];
    }

    public function messages(): array
    {
        return [
            'confirmation.in' => 'You must send "REINSTALL" to confirm this action.',
        ];
    }
}
