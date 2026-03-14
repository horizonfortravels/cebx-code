<?php

namespace App\Http\Requests;

use App\Models\Account;
use App\Models\Store;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * FR-IAM-009: Create/Update Store Validation
 */
class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled in service
    }

    public function rules(): array
    {
        $isUpdate = $this->isMethod('PUT') || $this->isMethod('PATCH');

        return [
            'name'           => [$isUpdate ? 'sometimes' : 'required', 'string', 'min:2', 'max:150'],
            'platform'       => ['sometimes', Rule::in(Store::ALL_PLATFORMS)],
            'contact_name'   => ['sometimes', 'nullable', 'string', 'max:150'],
            'contact_phone'  => ['sometimes', 'nullable', 'string', 'max:20'],
            'contact_email'  => ['sometimes', 'nullable', 'email', 'max:255'],
            'address_line_1' => ['sometimes', 'nullable', 'string', 'max:255'],
            'address_line_2' => ['sometimes', 'nullable', 'string', 'max:255'],
            'city'           => ['sometimes', 'nullable', 'string', 'max:100'],
            'state_province' => ['sometimes', 'nullable', 'string', 'max:100'],
            'postal_code'    => ['sometimes', 'nullable', 'string', 'max:20'],
            'country'        => ['sometimes', Rule::in(Account::SUPPORTED_COUNTRIES)],
            'currency'       => ['sometimes', Rule::in(Account::SUPPORTED_CURRENCIES)],
            'language'       => ['sometimes', Rule::in(Account::SUPPORTED_LANGUAGES)],
            'timezone'       => ['sometimes', Rule::in(Account::SUPPORTED_TIMEZONES)],
            'website_url'    => ['sometimes', 'nullable', 'url', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'اسم المتجر مطلوب.',
            'name.min'      => 'اسم المتجر يجب أن يكون حرفين على الأقل.',
            'platform.in'   => 'المنصة المختارة غير مدعومة.',
        ];
    }
}
