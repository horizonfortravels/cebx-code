<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'         => ['sometimes', 'string', 'min:2', 'max:100', 'regex:/^[a-z0-9_-]+$/'],
            'display_name' => ['sometimes', 'string', 'min:2', 'max:150'],
            'description'  => ['nullable', 'string', 'max:500'],
            'permissions'  => ['sometimes', 'array'],
            'permissions.*'=> ['string', 'max:100'],
        ];
    }
}
