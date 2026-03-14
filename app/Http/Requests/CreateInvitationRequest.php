<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Auth handled by middleware + service
    }

    public function rules(): array
    {
        return [
            'email'     => ['required', 'email:rfc,dns', 'max:255'],
            'name'      => ['nullable', 'string', 'min:2', 'max:150'],
            'role_id'   => ['nullable', 'uuid'],
            'ttl_hours' => ['nullable', 'integer', 'min:1', 'max:720'], // Max 30 days
        ];
    }

    public function messages(): array
    {
        return [
            'email.required'   => 'البريد الإلكتروني مطلوب.',
            'email.email'      => 'يرجى إدخال بريد إلكتروني صالح.',
            'name.min'         => 'الاسم يجب أن يكون حرفين على الأقل.',
            'role_id.uuid'     => 'معرّف الدور غير صالح.',
            'ttl_hours.min'    => 'مدة الصلاحية يجب أن تكون ساعة واحدة على الأقل.',
            'ttl_hours.max'    => 'مدة الصلاحية لا يمكن أن تتجاوز 30 يوماً.',
        ];
    }
}
