<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('notifications')) {
            return;
        }

        Schema::table('notifications', function (Blueprint $table): void {
            if (! Schema::hasColumn('notifications', 'event_type')) {
                $table->string('event_type', 100)->nullable()->after('user_id');
            }

            if (! Schema::hasColumn('notifications', 'entity_type')) {
                $table->string('entity_type', 100)->nullable()->after('event_type');
            }

            if (! Schema::hasColumn('notifications', 'entity_id')) {
                $table->string('entity_id', 100)->nullable()->after('entity_type');
            }

            if (! Schema::hasColumn('notifications', 'event_data')) {
                $table->json('event_data')->nullable()->after('entity_id');
            }

            if (! Schema::hasColumn('notifications', 'channel')) {
                $table->string('channel', 50)->nullable()->after('event_data');
            }

            if (! Schema::hasColumn('notifications', 'destination')) {
                $table->string('destination', 500)->nullable()->after('channel');
            }

            if (! Schema::hasColumn('notifications', 'language')) {
                $table->string('language', 5)->default('ar')->after('destination');
            }

            if (! Schema::hasColumn('notifications', 'subject')) {
                $table->string('subject', 500)->nullable()->after('title');
            }

            if (! Schema::hasColumn('notifications', 'template_id')) {
                $table->string('template_id', 100)->nullable()->after('subject');
            }

            if (! Schema::hasColumn('notifications', 'status')) {
                $table->string('status', 50)->default('pending')->after('template_id');
            }

            if (! Schema::hasColumn('notifications', 'retry_count')) {
                $table->integer('retry_count')->default(0)->after('status');
            }

            if (! Schema::hasColumn('notifications', 'max_retries')) {
                $table->integer('max_retries')->default(3)->after('retry_count');
            }

            if (! Schema::hasColumn('notifications', 'next_retry_at')) {
                $table->timestamp('next_retry_at')->nullable()->after('max_retries');
            }

            if (! Schema::hasColumn('notifications', 'failure_reason')) {
                $table->text('failure_reason')->nullable()->after('next_retry_at');
            }

            if (! Schema::hasColumn('notifications', 'external_id')) {
                $table->string('external_id', 200)->nullable()->after('failure_reason');
            }

            if (! Schema::hasColumn('notifications', 'is_batched')) {
                $table->boolean('is_batched')->default(false)->after('external_id');
            }

            if (! Schema::hasColumn('notifications', 'batch_id')) {
                $table->string('batch_id', 100)->nullable()->after('is_batched');
            }

            if (! Schema::hasColumn('notifications', 'is_throttled')) {
                $table->boolean('is_throttled')->default(false)->after('batch_id');
            }

            if (! Schema::hasColumn('notifications', 'scheduled_at')) {
                $table->timestamp('scheduled_at')->nullable()->after('is_throttled');
            }

            if (! Schema::hasColumn('notifications', 'sent_at')) {
                $table->timestamp('sent_at')->nullable()->after('scheduled_at');
            }

            if (! Schema::hasColumn('notifications', 'delivered_at')) {
                $table->timestamp('delivered_at')->nullable()->after('sent_at');
            }

            if (! Schema::hasColumn('notifications', 'provider')) {
                $table->string('provider', 100)->nullable()->after('delivered_at');
            }

            if (! Schema::hasColumn('notifications', 'provider_response')) {
                $table->json('provider_response')->nullable()->after('provider');
            }
        });

        DB::table('notifications')
            ->orderBy('id')
            ->get()
            ->each(function (object $notification): void {
                DB::table('notifications')
                    ->where('id', $notification->id)
                    ->update([
                        'event_type' => $this->stringOrFallback($notification->event_type ?? null, $notification->type ?? null, 'system.notification'),
                        'channel' => $this->stringOrFallback($notification->channel ?? null, 'in_app'),
                        'destination' => $this->stringOrFallback($notification->destination ?? null, $notification->user_id ?? null, $notification->account_id ?? null, 'broadcast'),
                        'subject' => $this->stringOrFallback($notification->subject ?? null, $notification->title ?? null, 'إشعار جديد'),
                        'status' => $this->stringOrFallback($notification->status ?? null, 'sent'),
                        'retry_count' => is_numeric($notification->retry_count ?? null) ? (int) $notification->retry_count : 0,
                        'max_retries' => is_numeric($notification->max_retries ?? null) ? (int) $notification->max_retries : 3,
                        'is_batched' => (bool) ($notification->is_batched ?? false),
                        'is_throttled' => (bool) ($notification->is_throttled ?? false),
                        'language' => $this->stringOrFallback($notification->language ?? null, 'ar'),
                    ]);
            });
    }

    public function down(): void
    {
        // Forward-only compatibility migration.
    }

    private function stringOrFallback(mixed ...$values): string
    {
        foreach ($values as $value) {
            $resolved = trim((string) ($value ?? ''));
            if ($resolved !== '') {
                return $resolved;
            }
        }

        return '';
    }
};
