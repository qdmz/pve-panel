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
            'name'          => ['sometimes', 'string', 'max:255'],
            'cpu'           => ['sometimes', 'integer', 'min:1', 'max:128'],
            'memory'        => ['sometimes', 'integer', 'min:128', 'max:1048576'],
            'disk'          => ['sometimes', 'integer', 'min:1', 'max:1048576'],
            'bandwidth'     => ['sometimes', 'integer', 'min:1'],
            'traffic_limit' => ['sometimes', 'integer', 'min:0'],
            'traffic_used'  => ['sometimes', 'integer', 'min:0'],
            'status'        => ['sometimes', 'string', 'in:running,stopped,suspended,error,creating'],
            'notes'         => ['nullable', 'string'],
            'expires_at'    => ['nullable', 'date'],
            'os_template'   => ['nullable', 'string', 'max:255'],
        ];
    }
}
