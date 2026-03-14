<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class AddUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled in UserService
    }

    public function rules(): array
    {
        return [
            'name'     => ['required', 'string', 'min:2', 'max:150'],
            'email'    => ['required', 'email:rfc,dns', 'max:255'],
            'password' => ['nullable', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
            'phone'    => ['nullable', 'string', 'max:20'],
            'locale'   => ['sometimes', 'string', 'max:10'],
            'timezone' => ['sometimes', 'string', 'max:50', 'timezone:all'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'ERR_INVALID_INPUT: اسم المستخدم مطلوب.',
            'email.required' => 'ERR_INVALID_INPUT: البريد الإلكتروني مطلوب.',
            'email.email'    => 'ERR_INVALID_INPUT: صيغة البريد الإلكتروني غير صحيحة.',
        ];
    }
}
