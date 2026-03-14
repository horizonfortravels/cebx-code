<?php

namespace App\Http\Requests;

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
            'name'     => ['sometimes', 'string', 'min:2', 'max:150'],
            'phone'    => ['sometimes', 'nullable', 'string', 'max:20'],
            'locale'   => ['sometimes', 'string', 'max:10'],
            'timezone' => ['sometimes', 'string', 'max:50', 'timezone:all'],
        ];
    }
}
