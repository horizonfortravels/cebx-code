<?php
namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        \App\Events\ShipmentCreated::class => [\App\Listeners\NotifyShipmentCreated::class, \App\Listeners\LogShipmentCreated::class],
        \App\Events\ShipmentStatusChanged::class => [\App\Listeners\NotifyStatusChange::class, \App\Listeners\UpdateTrackingTimeline::class],
        \App\Events\OrderCreated::class => [\App\Listeners\NotifyOrderCreated::class],
        \App\Events\WalletTopup::class => [\App\Listeners\LogWalletTopup::class],
        \App\Events\KycSubmitted::class => [\App\Listeners\NotifyKycSubmission::class],
    ];
}
