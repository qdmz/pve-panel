<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class CreateAnnouncementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'   => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'type'    => ['nullable', 'string', 'in:info,warning,success,danger'],
            'status'  => ['sometimes', 'string', 'in:draft,published'],
        ];
    }
}
