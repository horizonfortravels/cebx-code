<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * الكود في ShipmentWebController و OrderWebController يستخدم أعمدة من schema قديم:
 * user_id, type, weight, pieces, content_description, shipping_cost, vat_amount
 * وجدول shipments (2026) يعرّف created_by و total_weight و parcels_count و shipping_rate فقط.
 * نضيف الأعمدة المتوافقة مع نموذج الإنشاء، ونوسّع status و source ليقبلا 'pending' و 'manual'.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            if (!Schema::hasColumn('shipments', 'user_id')) {
                $table->uuid('user_id')->nullable()->after('account_id')->comment('Creator user; compat with created_by');
                $table->index('user_id');
            }
            if (!Schema::hasColumn('shipments', 'type')) {
                $table->string('type', 30)->nullable()->after('source')->comment('e.g. domestic, international');
            }
            if (!Schema::hasColumn('shipments', 'weight')) {
                $table->decimal('weight', 10, 2)->nullable()->after('parcels_count')->comment('Compat with total_weight');
            }
            if (!Schema::hasColumn('shipments', 'pieces')) {
                $table->integer('pieces')->default(1)->after('weight')->comment('Compat with parcels_count');
            }
            if (!Schema::hasColumn('shipments', 'content_description')) {
                $table->string('content_description', 500)->nullable()->after('pieces');
            }
            if (!Schema::hasColumn('shipments', 'shipping_cost')) {
                $table->decimal('shipping_cost', 15, 2)->nullable()->after('total_charge')->comment('Compat with shipping_rate');
            }
            if (!Schema::hasColumn('shipments', 'vat_amount')) {
                $table->decimal('vat_amount', 15, 2)->nullable()->after('shipping_cost');
            }
        });

        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            $this->addEnumValue('shipments', 'status', 'pending', [
                'draft', 'validated', 'rated', 'payment_pending', 'purchased', 'ready_for_pickup',
                'picked_up', 'in_transit', 'out_for_delivery', 'delivered', 'returned',
                'exception', 'cancelled', 'failed',
            ], 'draft');
            $this->addEnumValue('shipments', 'source', 'manual', ['direct', 'order', 'bulk', 'return'], 'direct');
        }
    }

    private function addEnumValue(string $table, string $column, string $newValue, array $existingValues, string $default): void
    {
        if (in_array($newValue, $existingValues)) {
            return;
        }
        $allValues = array_merge($existingValues, [$newValue]);
        $enumList = implode("','", array_map(fn($v) => str_replace("'", "''", $v), $allValues));
        $def = DB::selectOne("SHOW COLUMNS FROM `{$table}` WHERE Field = ?", [$column]);
        if (!$def || stripos((string) $def->Type, "'" . $newValue . "'") !== false) {
            return;
        }
        DB::statement("ALTER TABLE `{$table}` MODIFY COLUMN `{$column}` ENUM('{$enumList}') DEFAULT " . DB::getPdo()->quote($default));
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $cols = ['user_id', 'type', 'weight', 'pieces', 'content_description', 'shipping_cost', 'vat_amount'];
            foreach ($cols as $c) {
                if (Schema::hasColumn('shipments', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
};
