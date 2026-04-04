<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('webhook_events')) {
            return;
        }

        Schema::create('webhook_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('account_id');
            $table->uuid('store_id');
            $table->string('platform', 30);
            $table->string('event_type', 100);
            $table->string('external_event_id', 255)->nullable();
            $table->string('external_resource_id', 200)->nullable();
            $table->enum('status', ['received', 'processing', 'processed', 'failed', 'duplicate', 'ignored'])
                ->default('received');
            $table->json('payload')->nullable();
            $table->text('error_message')->nullable();
            $table->integer('retry_count')->default(0);
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['store_id', 'external_event_id'], 'webhook_events_dedup');
            $table->index(['account_id', 'store_id', 'status']);
            $table->index(['store_id', 'event_type', 'created_at']);
            $table->index('account_id');
            $table->index('store_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
    }
};
