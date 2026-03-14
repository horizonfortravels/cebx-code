<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\ContentDeclaration;
use Illuminate\Database\Eloquent\Factories\Factory;

class ContentDeclarationFactory extends Factory
{
    protected $model = ContentDeclaration::class;

    public function definition(): array
    {
        return [
            'account_id'                => Account::factory(),
            'shipment_id'               => 'SH-' . $this->faker->unique()->numerify('####'),
            'contains_dangerous_goods'  => false,
            'status'                    => ContentDeclaration::STATUS_PENDING,
            'declared_by'               => $this->faker->uuid(),
            'ip_address'                => $this->faker->ipv4(),
            'user_agent'                => 'Mozilla/5.0',
            'locale'                    => 'ar',
            'declared_at'               => now(),
        ];
    }

    public function completed(): static
    {
        return $this->state([
            'contains_dangerous_goods' => false,
            'status'                   => ContentDeclaration::STATUS_COMPLETED,
            'waiver_accepted'          => true,
            'waiver_accepted_at'       => now(),
        ]);
    }

    public function holdDg(): static
    {
        return $this->state([
            'contains_dangerous_goods' => true,
            'status'                   => ContentDeclaration::STATUS_HOLD_DG,
            'hold_reason'              => 'المواد الخطرة غير مدعومة في الإصدار الحالي.',
        ]);
    }

    public function withDg(): static
    {
        return $this->state(['contains_dangerous_goods' => true, 'status' => ContentDeclaration::STATUS_HOLD_DG]);
    }
}
