<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class AcceptInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Public endpoint - no auth required
    }

    public function rules(): array
    {
        return [
            'name'     => ['required', 'string', 'min:2', 'max:150'],
            'password' => ['required', 'string', Password::min(8)->mixedCase()->numbers()->symbols()],
            'phone'    => ['nullable', 'string', 'max:20'],
            'locale'   => ['nullable', 'string', 'in:en,ar'],
            'timezone' => ['nullable', 'string', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'     => 'الاسم مطلوب.',
            'name.min'          => 'الاسم يجب أن يكون حرفين على الأقل.',
            'password.required' => 'كلمة المرور مطلوبة.',
        ];
    }
}
