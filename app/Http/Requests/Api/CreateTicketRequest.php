<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class CreateTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'subject'    => ['required', 'string', 'max:255'],
            'department' => ['required', 'string', 'in:support,billing,technical,sales'],
            'priority'   => ['required', 'string', 'in:low,medium,high,urgent'],
            'content'    => ['required', 'string', 'min:10'],
        ];
    }
}
