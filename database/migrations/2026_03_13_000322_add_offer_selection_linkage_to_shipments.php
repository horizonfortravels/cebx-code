<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('shipments')) {
            return;
        }

        Schema::table('shipments', function (Blueprint $table): void {
            if (! Schema::hasColumn('shipments', 'rate_quote_id')) {
                $table->uuid('rate_quote_id')->nullable()->after('status_reason');
                $table->index('rate_quote_id');
            }

            if (! Schema::hasColumn('shipments', 'selected_rate_option_id')) {
                $table->uuid('selected_rate_option_id')->nullable()->after('rate_quote_id');
                $table->index('selected_rate_option_id');
            }
        });

        if (DB::getDriverName() !== 'mysql' || ! Schema::hasColumn('shipments', 'status')) {
            return;
        }

        $definition = DB::selectOne("SHOW COLUMNS FROM `shipments` WHERE Field = 'status'");
        if (! $definition) {
            return;
        }

        $type = (string) ($definition->Type ?? '');
        if (! str_starts_with(strtolower($type), 'enum(') || str_contains($type, "'offer_selected'")) {
            return;
        }

        $enumValues = $this->extractEnumValues($type);
        $position = array_search('rated', $enumValues, true);

        if ($position === false) {
            $enumValues[] = 'offer_selected';
        } else {
            array_splice($enumValues, $position + 1, 0, ['offer_selected']);
        }

        $enumValues = array_values(array_unique($enumValues));
        $enumList = implode(
            "','",
            array_map(static fn (string $value): string => str_replace("'", "''", $value), $enumValues)
        );

        DB::statement("ALTER TABLE `shipments` MODIFY COLUMN `status` ENUM('{$enumList}') DEFAULT 'draft'");
    }

    public function down(): void
    {
        // Forward-only migration policy: never edit previously-run historical migrations.
    }

    /**
     * @return array<int, string>
     */
    private function extractEnumValues(string $type): array
    {
        preg_match_all("/'((?:[^'\\\\]|\\\\.)*)'/", $type, $matches);

        return array_map(
            static fn (string $value): string => stripcslashes($value),
            $matches[1] ?? []
        );
    }
};
