<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * جدول shipment_events مطلوب من ShipmentWebController (أحداث التتبع).
 * لم يكن منشأً لأن migration 0001_01_01_000001 لم يُنفَّذ.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('shipment_events')) {
            return;
        }

        Schema::create('shipment_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('shipment_id')->constrained('shipments')->cascadeOnDelete();
            $table->string('status', 50)->nullable();
            $table->string('description')->nullable();
            $table->string('location')->nullable();
            $table->timestamp('event_at');
            $table->timestamps();

            $table->index(['shipment_id', 'event_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_events');
    }
};
