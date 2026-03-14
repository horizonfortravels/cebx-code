<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class NotificationFactory extends Factory
{
    protected $model = Notification::class;

    public function definition(): array
    {
        return [
            'account_id'  => Account::factory(),
            'user_id'     => User::factory(),
            'event_type'  => Notification::EVENT_SHIPMENT_DELIVERED,
            'entity_type' => 'shipment',
            'entity_id'   => $this->faker->uuid(),
            'event_data'  => ['tracking_number' => '1234567890'],
            'channel'     => Notification::CHANNEL_EMAIL,
            'destination' => $this->faker->email(),
            'language'    => 'ar',
            'subject'     => 'تم تسليم شحنتك',
            'body'        => 'تم تسليم الشحنة بنجاح',
            'status'      => Notification::STATUS_SENT,
            'sent_at'     => now(),
        ];
    }

    public function pending(): static { return $this->state(['status' => Notification::STATUS_PENDING, 'sent_at' => null]); }
    public function failed(): static { return $this->state(['status' => Notification::STATUS_FAILED, 'failure_reason' => 'Connection refused']); }
    public function retrying(): static { return $this->state(['status' => Notification::STATUS_RETRYING, 'retry_count' => 1, 'next_retry_at' => now()->subMinute()]); }
    public function dlq(): static { return $this->state(['status' => Notification::STATUS_DLQ, 'retry_count' => 3]); }
    public function inApp(): static { return $this->state(['channel' => Notification::CHANNEL_IN_APP, 'destination' => $this->faker->uuid()]); }
    public function sms(): static { return $this->state(['channel' => Notification::CHANNEL_SMS, 'destination' => $this->faker->phoneNumber()]); }
    public function throttled(): static { return $this->state(['is_throttled' => true, 'status' => Notification::STATUS_PENDING]); }
    public function batched(): static { return $this->state(['is_batched' => true, 'batch_id' => 'digest_test']); }
}
