<?php

namespace Database\Factories;

use App\Models\VerificationCase;
use App\Models\VerificationDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

class VerificationDocumentFactory extends Factory
{
    protected $model = VerificationDocument::class;

    public function definition(): array
    {
        return [
            'case_id'           => VerificationCase::factory(),
            'document_type'     => 'national_id',
            'original_filename' => 'id_card.pdf',
            'stored_path'       => '/secure/kyc/' . $this->faker->uuid() . '.enc',
            'mime_type'         => 'application/pdf',
            'file_size'         => 1024000,
            'file_hash'         => hash('sha256', $this->faker->uuid()),
            'status'            => 'uploaded',
            'is_encrypted'      => true,
            'uploaded_at'       => now(),
            'uploaded_by'       => $this->faker->uuid(),
        ];
    }
}
