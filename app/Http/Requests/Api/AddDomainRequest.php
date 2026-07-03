<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class AddDomainRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'domain'      => ['required', 'string', 'max:255'],
            'target_port' => ['required', 'integer', 'min:1', 'max:65535'],
            'ssl_enabled' => ['boolean'],
        ];
    }
}
