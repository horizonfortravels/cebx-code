<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\VerificationCase;
use Illuminate\Database\Eloquent\Factories\Factory;

class VerificationCaseFactory extends Factory
{
    protected $model = VerificationCase::class;

    public function definition(): array
    {
        return [
            'account_id'         => Account::factory(),
            'case_number'        => VerificationCase::generateCaseNumber(),
            'account_type'       => 'individual',
            'status'             => VerificationCase::STATUS_UNVERIFIED,
            'applicant_name'     => 'أحمد محمد',
            'applicant_email'    => $this->faker->email(),
            'country_code'       => 'SA',
            'required_documents' => ['national_id'],
        ];
    }

    public function organization(): static { return $this->state(['account_type' => 'organization', 'required_documents' => ['commercial_register', 'tax_certificate']]); }
    public function pending(): static { return $this->state(['status' => VerificationCase::STATUS_PENDING_REVIEW, 'submitted_at' => now()]); }
    public function verified(): static { return $this->state(['status' => VerificationCase::STATUS_VERIFIED, 'verified_at' => now()]); }
    public function rejected(): static { return $this->state(['status' => VerificationCase::STATUS_REJECTED, 'rejection_reason' => 'Docs unclear']); }
}
