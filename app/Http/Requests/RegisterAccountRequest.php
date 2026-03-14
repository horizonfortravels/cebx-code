<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Public registration endpoint
    }

    public function rules(): array
    {
        return [
            // Account info
            'account_name' => ['required', 'string', 'min:2', 'max:150'],
            'account_type' => ['sometimes', 'in:individual,organization'],

            // Owner user info
            'name'     => ['required', 'string', 'min:2', 'max:150'],
            'email'    => ['required', 'email:rfc,dns', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
            'phone'    => ['nullable', 'string', 'max:20'],
            'locale'   => ['sometimes', 'string', 'max:10'],
            'timezone' => ['sometimes', 'string', 'max:50', 'timezone:all'],

            // Organization fields (optional at registration, required for KYC later)
            'legal_name'          => ['nullable', 'string', 'max:200'],
            'trade_name'          => ['nullable', 'string', 'max:200'],
            'registration_number' => ['nullable', 'string', 'max:100'],
            'tax_id'              => ['nullable', 'string', 'max:100'],
            'industry'            => ['nullable', 'string', 'max:100'],
            'company_size'        => ['nullable', 'in:small,medium,large,enterprise'],
            'country'             => ['nullable', 'string', 'max:3'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique'        => 'ERR_DUPLICATE_EMAIL: هذا البريد الإلكتروني مستخدم بالفعل.',
            'account_name.max'    => 'ERR_INVALID_INPUT: اسم الحساب يتجاوز الحد الأقصى (150 حرف).',
            'account_name.min'    => 'ERR_INVALID_INPUT: اسم الحساب قصير جداً.',
            'password.min'        => 'ERR_INVALID_INPUT: كلمة المرور ضعيفة.',
        ];
    }
}
