<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class AddNatRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'local_port'  => ['required', 'integer', 'min:1', 'max:65535'],
            'public_port' => ['required', 'integer', 'min:1', 'max:65535'],
            'protocol'    => ['required', 'string', 'in:tcp,udp,both'],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }
}
