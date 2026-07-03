<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class SubmitVerificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'real_name'       => ['required', 'string', 'max:100'],
            'id_type'         => ['required', 'string', 'in:id_card,passport,driver_license'],
            'id_number'       => ['required', 'string', 'max:50'],
            'id_front_photo'  => ['required', 'string'],
            'id_back_photo'   => ['required', 'string'],
        ];
    }
}
