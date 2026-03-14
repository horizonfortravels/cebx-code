<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrganizationProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'legal_name'          => ['sometimes', 'string', 'min:2', 'max:200'],
            'trade_name'          => ['nullable', 'string', 'max:200'],
            'registration_number' => ['nullable', 'string', 'max:100'],
            'tax_id'              => ['nullable', 'string', 'max:100'],
            'industry'            => ['nullable', 'string', 'max:100'],
            'company_size'        => ['nullable', 'in:small,medium,large,enterprise'],
            'country'             => ['nullable', 'string', 'max:3'],
            'city'                => ['nullable', 'string', 'max:100'],
            'address_line_1'      => ['nullable', 'string', 'max:255'],
            'address_line_2'      => ['nullable', 'string', 'max:255'],
            'postal_code'         => ['nullable', 'string', 'max:20'],
            'phone'               => ['nullable', 'string', 'max:20'],
            'email'               => ['nullable', 'email', 'max:255'],
            'website'             => ['nullable', 'url', 'max:255'],
            'billing_currency'    => ['nullable', 'string', 'size:3'],
            'billing_cycle'       => ['nullable', 'in:monthly,weekly,per-shipment'],
            'billing_email'       => ['nullable', 'email', 'max:255'],
        ];
    }
}
