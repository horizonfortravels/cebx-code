<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\KycVerification;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class KycVerificationFactory extends Factory
{
    protected $model = KycVerification::class;

    public function definition(): array
    {
        return [
            'id'                  => Str::uuid()->toString(),
            'account_id'          => Account::factory(),
            'status'              => KycVerification::STATUS_UNVERIFIED,
            'verification_type'   => 'individual',
            'verification_level'  => 'basic',
            'required_documents'  => ['national_id' => 'الهوية الوطنية', 'address_proof' => 'إثبات العنوان'],
            'submitted_documents' => null,
            'review_count'        => 0,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => [
            'status'              => KycVerification::STATUS_PENDING,
            'submitted_documents' => ['national_id' => 'path/to/id.pdf'],
            'submitted_at'        => now(),
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'status'      => KycVerification::STATUS_APPROVED,
            'reviewed_at' => now(),
            'expires_at'  => now()->addYear(),
            'review_count'=> 1,
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => [
            'status'           => KycVerification::STATUS_REJECTED,
            'rejection_reason' => 'وثائق غير واضحة',
            'reviewed_at'      => now(),
            'review_count'     => 1,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'status'     => KycVerification::STATUS_EXPIRED,
            'expires_at' => now()->subDay(),
        ]);
    }

    public function organization(): static
    {
        return $this->state(fn () => [
            'verification_type'  => 'organization',
            'required_documents' => [
                'commercial_registration' => 'السجل التجاري',
                'tax_certificate' => 'شهادة الضريبة',
            ],
        ]);
    }
}
