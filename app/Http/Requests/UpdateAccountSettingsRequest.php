<?php

namespace App\Http\Requests;

use App\Models\Account;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * FR-IAM-008: Account Settings Update Validation
 */
class UpdateAccountSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled in service
    }

    public function rules(): array
    {
        return [
            'name'           => ['sometimes', 'string', 'min:2', 'max:150'],
            'language'       => ['sometimes', Rule::in(Account::SUPPORTED_LANGUAGES)],
            'currency'       => ['sometimes', Rule::in(Account::SUPPORTED_CURRENCIES)],
            'timezone'       => ['sometimes', Rule::in(Account::SUPPORTED_TIMEZONES)],
            'country'        => ['sometimes', Rule::in(Account::SUPPORTED_COUNTRIES)],
            'contact_phone'  => ['sometimes', 'nullable', 'string', 'max:20'],
            'contact_email'  => ['sometimes', 'nullable', 'email', 'max:255'],
            'address_line_1' => ['sometimes', 'nullable', 'string', 'max:255'],
            'address_line_2' => ['sometimes', 'nullable', 'string', 'max:255'],
            'city'           => ['sometimes', 'nullable', 'string', 'max:100'],
            'postal_code'    => ['sometimes', 'nullable', 'string', 'max:20'],
            'date_format'    => ['sometimes', Rule::in(Account::SUPPORTED_DATE_FORMATS)],
            'weight_unit'    => ['sometimes', Rule::in(Account::SUPPORTED_WEIGHT_UNITS)],
            'dimension_unit' => ['sometimes', Rule::in(Account::SUPPORTED_DIMENSION_UNITS)],
            'extended'       => ['sometimes', 'array'],
            'extended.*'     => ['nullable'],
        ];
    }

    public function messages(): array
    {
        return [
            'language.in'       => 'اللغة المختارة غير مدعومة.',
            'currency.in'       => 'العملة المختارة غير مدعومة.',
            'timezone.in'       => 'المنطقة الزمنية غير مدعومة.',
            'country.in'        => 'الدولة المختارة غير مدعومة.',
            'date_format.in'    => 'صيغة التاريخ غير مدعومة.',
            'weight_unit.in'    => 'وحدة الوزن غير مدعومة.',
            'dimension_unit.in' => 'وحدة الأبعاد غير مدعومة.',
            'name.min'          => 'اسم الحساب يجب أن يكون حرفين على الأقل.',
        ];
    }
}
