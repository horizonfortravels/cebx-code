<?php

namespace Database\Factories;

use App\Models\WaiverVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

class WaiverVersionFactory extends Factory
{
    protected $model = WaiverVersion::class;

    public function definition(): array
    {
        $text = 'أقر بأن محتويات هذه الشحنة لا تحتوي على مواد خطرة وأنني أتحمل المسؤولية الكاملة.';

        return [
            'version'     => '1.0',
            'locale'      => 'ar',
            'waiver_text' => $text,
            'waiver_hash' => hash('sha256', $text),
            'is_active'   => true,
        ];
    }

    public function english(): static
    {
        $text = 'I hereby declare that this shipment does not contain dangerous goods and I accept full liability.';
        return $this->state([
            'locale'      => 'en',
            'waiver_text' => $text,
            'waiver_hash' => hash('sha256', $text),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
