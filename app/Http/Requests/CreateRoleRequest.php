<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization in service layer
    }

    public function rules(): array
    {
        return [
            'name'         => ['required', 'string', 'min:2', 'max:100', 'regex:/^[a-z0-9_-]+$/'],
            'display_name' => ['required', 'string', 'min:2', 'max:150'],
            'description'  => ['nullable', 'string', 'max:500'],
            'template'     => ['nullable', 'string', 'max:50'],
            'permissions'  => ['nullable', 'array'],
            'permissions.*'=> ['string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.regex' => 'اسم الدور يجب أن يحتوي فقط على أحرف صغيرة وأرقام وشرطات.',
        ];
    }
}
