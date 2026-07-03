<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class CreateNodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $authType = $this->input('auth_type', 'api_token');

        return [
            'name'           => ['required', 'string', 'max:100'],
            'host'           => ['required', 'string', 'max:255'],
            'port'           => ['nullable', 'integer', 'min:1', 'max:65535'],
            'auth_type'      => ['nullable', 'string', 'in:api_token,username_password'],
            'api_token'      => ['required_if:auth_type,api_token', 'nullable', 'string'],
            'username'       => ['required_if:auth_type,username_password', 'nullable', 'string', 'max:255'],
            'password'       => ['required_if:auth_type,username_password', 'nullable', 'string', 'max:255'],
            'virtualization' => ['nullable', 'string', 'in:kvm,lxc,both'],
            'bridge'         => ['nullable', 'string', 'max:50'],
            'ipv6_bridge'    => ['nullable', 'string', 'max:50'],
            'storage'        => ['nullable', 'string', 'max:100'],
            'nat_enabled'    => ['nullable', 'boolean'],
            'nat_start_port' => ['nullable', 'integer'],
            'nat_end_port'   => ['nullable', 'integer'],
            'nat_network'    => ['nullable', 'string', 'max:100'],
        ];
    }
}
