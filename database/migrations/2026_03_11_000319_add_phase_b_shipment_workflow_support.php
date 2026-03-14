<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('shipments')) {
            Schema::table('shipments', function (Blueprint $table) {
                if (! Schema::hasColumn('shipments', 'sender_address_1')) {
                    $table->string('sender_address_1', 300)->nullable()->after('sender_address');
                }
                if (! Schema::hasColumn('shipments', 'sender_postal_code')) {
                    $table->string('sender_postal_code', 20)->nullable()->after('sender_city');
                }
                if (! Schema::hasColumn('shipments', 'sender_country')) {
                    $table->string('sender_country', 2)->nullable()->after('sender_postal_code');
                }
                if (! Schema::hasColumn('shipments', 'recipient_address_1')) {
                    $table->string('recipient_address_1', 300)->nullable()->after('recipient_address');
                }
                if (! Schema::hasColumn('shipments', 'is_international')) {
                    $table->boolean('is_international')->default(false)->after('recipient_postal_code');
                }
                if (! Schema::hasColumn('shipments', 'status_reason')) {
                    $table->string('status_reason', 500)->nullable()->after('status');
                }
                if (! Schema::hasColumn('shipments', 'kyc_verified')) {
                    $table->boolean('kyc_verified')->default(false)->after('is_international');
                }
                if (! Schema::hasColumn('shipments', 'total_weight')) {
                    $table->decimal('total_weight', 10, 3)->nullable()->after('weight');
                }
                if (! Schema::hasColumn('shipments', 'volumetric_weight')) {
                    $table->decimal('volumetric_weight', 10, 3)->nullable()->after('total_weight');
                }
                if (! Schema::hasColumn('shipments', 'chargeable_weight')) {
                    $table->decimal('chargeable_weight', 10, 3)->nullable()->after('volumetric_weight');
                }
                if (! Schema::hasColumn('shipments', 'parcels_count')) {
                    $table->integer('parcels_count')->default(1)->after('pieces');
                }
                if (! Schema::hasColumn('shipments', 'created_by')) {
                    $table->uuid('created_by')->nullable()->after('user_id');
                }
                if (! Schema::hasColumn('shipments', 'metadata')) {
                    $table->json('metadata')->nullable()->after('content_description');
                }
            });

            if (Schema::hasColumn('shipments', 'sender_address') && Schema::hasColumn('shipments', 'sender_address_1')) {
                DB::table('shipments')
                    ->whereNull('sender_address_1')
                    ->whereNotNull('sender_address')
                    ->update(['sender_address_1' => DB::raw('sender_address')]);
            }

            if (Schema::hasColumn('shipments', 'recipient_address') && Schema::hasColumn('shipments', 'recipient_address_1')) {
                DB::table('shipments')
                    ->whereNull('recipient_address_1')
                    ->whereNotNull('recipient_address')
                    ->update(['recipient_address_1' => DB::raw('recipient_address')]);
            }

            if (Schema::hasColumn('shipments', 'weight') && Schema::hasColumn('shipments', 'total_weight')) {
                DB::table('shipments')
                    ->whereNull('total_weight')
                    ->whereNotNull('weight')
                    ->update(['total_weight' => DB::raw('weight')]);
            }

            if (Schema::hasColumn('shipments', 'weight') && Schema::hasColumn('shipments', 'chargeable_weight')) {
                DB::table('shipments')
                    ->whereNull('chargeable_weight')
                    ->whereNotNull('weight')
                    ->update(['chargeable_weight' => DB::raw('weight')]);
            }

            if (Schema::hasColumn('shipments', 'pieces') && Schema::hasColumn('shipments', 'parcels_count')) {
                DB::table('shipments')
                    ->whereNull('parcels_count')
                    ->whereNotNull('pieces')
                    ->update(['parcels_count' => DB::raw('pieces')]);
            }

            if (Schema::hasColumn('shipments', 'user_id') && Schema::hasColumn('shipments', 'created_by')) {
                DB::table('shipments')
                    ->whereNull('created_by')
                    ->whereNotNull('user_id')
                    ->update(['created_by' => DB::raw('user_id')]);
            }

            if (Schema::hasColumn('shipments', 'sender_country') && Schema::hasColumn('shipments', 'recipient_country') && Schema::hasColumn('shipments', 'is_international')) {
                DB::table('shipments')
                    ->where(function ($query) {
                        $query->whereNull('sender_country')->orWhere('sender_country', '');
                    })
                    ->whereNotNull('recipient_country')
                    ->update(['sender_country' => DB::raw('recipient_country')]);

                DB::table('shipments')
                    ->whereNotNull('sender_country')
                    ->whereNotNull('recipient_country')
                    ->update(['is_international' => DB::raw('CASE WHEN sender_country <> recipient_country THEN 1 ELSE 0 END')]);
            }
        }

        if (! Schema::hasTable('parcels')) {
            Schema::create('parcels', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('shipment_id');
                $table->integer('sequence')->default(1);
                $table->decimal('weight', 10, 3);
                $table->decimal('length', 10, 2)->nullable();
                $table->decimal('width', 10, 2)->nullable();
                $table->decimal('height', 10, 2)->nullable();
                $table->decimal('volumetric_weight', 10, 3)->nullable();
                $table->string('packaging_type', 50)->default('custom');
                $table->string('description', 300)->nullable();
                $table->string('reference', 100)->nullable();
                $table->string('carrier_parcel_id', 200)->nullable();
                $table->string('carrier_tracking', 100)->nullable();
                $table->string('label_url', 500)->nullable();
                $table->timestamps();

                $table->index('shipment_id');
            });
        }

        if (! Schema::hasTable('shipment_status_history')) {
            Schema::create('shipment_status_history', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('shipment_id');
                $table->string('from_status', 30)->nullable();
                $table->string('to_status', 30);
                $table->string('source', 30)->default('system');
                $table->string('reason', 500)->nullable();
                $table->uuid('changed_by')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('created_at');

                $table->index(['shipment_id', 'created_at']);
                $table->index('changed_by');
            });
        }
    }

    public function down(): void
    {
        // Forward-only migration: historical schema must not be edited or reversed.
    }
};
