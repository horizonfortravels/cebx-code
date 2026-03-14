<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrganizationProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'account_id'          => $this->account_id,
            'legal_name'          => $this->legal_name,
            'trade_name'          => $this->trade_name,
            'registration_number' => $this->registration_number,
            'tax_id'              => $this->tax_id,
            'industry'            => $this->industry,
            'company_size'        => $this->company_size,
            'country'             => $this->country,
            'city'                => $this->city,
            'address_line_1'      => $this->address_line_1,
            'address_line_2'      => $this->address_line_2,
            'postal_code'         => $this->postal_code,
            'phone'               => $this->phone,
            'email'               => $this->email,
            'website'             => $this->website,
            'billing_currency'    => $this->billing_currency,
            'billing_cycle'       => $this->billing_cycle,
            'billing_email'       => $this->billing_email,
            'logo_path'           => $this->logo_path,
            'is_complete'         => $this->isComplete(),
            'created_at'          => $this->created_at?->toISOString(),
            'updated_at'          => $this->updated_at?->toISOString(),
        ];
    }
}
