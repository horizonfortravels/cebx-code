<?php
// ── NotificationTemplateFactory ──────────────────────────────
namespace Database\Factories;

use App\Models\Notification;
use App\Models\NotificationTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

class NotificationTemplateFactory extends Factory
{
    protected $model = NotificationTemplate::class;

    public function definition(): array
    {
        return [
            'account_id'  => null, // System default
            'event_type'  => Notification::EVENT_SHIPMENT_DELIVERED,
            'channel'     => Notification::CHANNEL_EMAIL,
            'language'    => 'ar',
            'subject'     => 'تم تسليم شحنتك #{{tracking_number}}',
            'body'        => 'مرحباً {{user_name}}, تم تسليم شحنتك برقم {{tracking_number}} بنجاح.',
            'body_html'   => '<h1>تم التسليم</h1><p>مرحباً {{user_name}}</p>',
            'variables'   => ['user_name', 'tracking_number', 'account_name'],
            'is_active'   => true,
            'is_system'   => false,
        ];
    }

    public function system(): static { return $this->state(['is_system' => true]); }
    public function english(): static { return $this->state(['language' => 'en', 'subject' => 'Shipment #{{tracking_number}} Delivered', 'body' => 'Hi {{user_name}}, your shipment was delivered.']); }
    public function sms(): static { return $this->state(['channel' => Notification::CHANNEL_SMS, 'subject' => null, 'body' => 'شحنتك {{tracking_number}} تم تسليمها', 'body_html' => null]); }
}
