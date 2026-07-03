<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class CreateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'            => ['required', 'string', 'max:255'],
            'type'            => ['required', 'string', 'in:kvm,lxc'],
            'cpu'             => ['required', 'integer', 'min:1'],
            'memory'          => ['required', 'integer', 'min:128'],
            'disk'            => ['required', 'integer', 'min:1'],
            'bandwidth'       => ['required', 'integer', 'min:1'],
            'traffic'         => ['nullable', 'integer', 'min:0'],
            'monthly_price'   => ['required', 'numeric', 'min:0'],
            'yearly_price'    => ['nullable', 'numeric', 'min:0'],
            'description'     => ['nullable', 'string'],
            'status'          => ['sometimes', 'string', 'in:active,inactive'],
            'sort_order'      => ['nullable', 'integer', 'min:0'],
            'stock'           => ['nullable', 'integer', 'min:-1'],
            'node_ids'        => ['nullable', 'array'],
            'template_ids'    => ['nullable', 'array'],
            'features'        => ['nullable', 'array'],
        ];
    }
}
