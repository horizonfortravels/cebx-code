<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\KycDocument;
use App\Models\KycVerification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class KycDocumentFactory extends Factory
{
    protected $model = KycDocument::class;

    public function definition(): array
    {
        $types = ['national_id', 'commercial_registration', 'tax_certificate', 'address_proof'];

        return [
            'id'                   => Str::uuid()->toString(),
            'account_id'           => Account::factory(),
            'kyc_verification_id'  => KycVerification::factory(),
            'document_type'        => $this->faker->randomElement($types),
            'original_filename'    => $this->faker->word() . '.pdf',
            'stored_path'          => 'kyc/' . Str::uuid() . '.pdf',
            'mime_type'            => 'application/pdf',
            'file_size'            => $this->faker->numberBetween(10000, 5000000),
            'file_hash'            => hash('sha256', $this->faker->text()),
            'uploaded_by'          => User::factory(),
            'is_sensitive'         => true,
            'is_purged'            => false,
        ];
    }

    public function purged(): static
    {
        return $this->state(fn () => [
            'is_purged'   => true,
            'purged_at'   => now(),
            'stored_path' => '[PURGED]',
        ]);
    }

    public function identity(): static
    {
        return $this->state(fn () => [
            'document_type' => 'national_id',
            'is_sensitive'  => true,
        ]);
    }
}
