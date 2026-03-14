<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FR-IAM-008: Account Settings
 *
 * Adds dedicated columns for core settings alongside existing JSONB settings.
 * Dedicated columns: language, currency, timezone, country, phone, email (contact).
 * JSONB settings: extended/custom preferences.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('accounts')) {
            return;
        }

        Schema::table('accounts', function (Blueprint $table) {
            if (! Schema::hasColumn('accounts', 'language')) {
                if (Schema::hasColumn('accounts', 'settings')) {
                    $table->string('language', 10)->default('ar')->after('settings');
                } else {
                    $table->string('language', 10)->default('ar');
                }
            }
            if (! Schema::hasColumn('accounts', 'currency')) {
                $table->string('currency', 3)->default('SAR')->after('language');
            }
            if (! Schema::hasColumn('accounts', 'timezone')) {
                $table->string('timezone', 50)->default('Asia/Riyadh')->after('currency');
            }
            if (! Schema::hasColumn('accounts', 'country')) {
                $table->string('country', 3)->default('SA')->after('timezone');
            }
            if (! Schema::hasColumn('accounts', 'contact_phone')) {
                $table->string('contact_phone', 20)->nullable()->after('country');
            }
            if (! Schema::hasColumn('accounts', 'contact_email')) {
                $table->string('contact_email', 255)->nullable()->after('contact_phone');
            }
            if (! Schema::hasColumn('accounts', 'address_line_1')) {
                $table->string('address_line_1', 255)->nullable()->after('contact_email');
            }
            if (! Schema::hasColumn('accounts', 'address_line_2')) {
                $table->string('address_line_2', 255)->nullable()->after('address_line_1');
            }
            if (! Schema::hasColumn('accounts', 'city')) {
                $table->string('city', 100)->nullable()->after('address_line_2');
            }
            if (! Schema::hasColumn('accounts', 'postal_code')) {
                $table->string('postal_code', 20)->nullable()->after('city');
            }
            if (! Schema::hasColumn('accounts', 'date_format')) {
                $table->string('date_format', 20)->default('Y-m-d')->after('postal_code');
            }
            if (! Schema::hasColumn('accounts', 'weight_unit')) {
                $table->string('weight_unit', 5)->default('kg')->after('date_format');
            }
            if (! Schema::hasColumn('accounts', 'dimension_unit')) {
                $table->string('dimension_unit', 5)->default('cm')->after('weight_unit');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('accounts')) {
            return;
        }

        Schema::table('accounts', function (Blueprint $table) {
            $columns = [
                'language', 'currency', 'timezone', 'country',
                'contact_phone', 'contact_email',
                'address_line_1', 'address_line_2', 'city', 'postal_code',
                'date_format', 'weight_unit', 'dimension_unit',
            ];
            foreach ($columns as $col) {
                if (Schema::hasColumn('accounts', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
