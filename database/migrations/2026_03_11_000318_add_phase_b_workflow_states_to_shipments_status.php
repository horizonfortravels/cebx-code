<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('shipments') || ! Schema::hasColumn('shipments', 'status')) {
            return;
        }

        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $definition = DB::selectOne("SHOW COLUMNS FROM `shipments` WHERE Field = 'status'");
        if (! $definition) {
            return;
        }

        $type = (string) ($definition->Type ?? '');
        if (! str_starts_with(strtolower($type), 'enum(')) {
            return;
        }

        foreach (['kyc_blocked', 'ready_for_rates'] as $state) {
            if (str_contains($type, "'" . $state . "'")) {
                continue;
            }

            $enumValues = $this->extractEnumValues($type);
            $insertAfter = 'validated';
            $position = array_search($insertAfter, $enumValues, true);

            if ($position === false) {
                $enumValues[] = $state;
            } else {
                array_splice($enumValues, $position + 1, 0, [$state]);
            }

            $enumList = implode("','", array_map(static fn (string $value): string => str_replace("'", "''", $value), array_values(array_unique($enumValues))));
            DB::statement("ALTER TABLE `shipments` MODIFY COLUMN `status` ENUM('{$enumList}') DEFAULT 'draft'");
            $type = "enum('{$enumList}')";
        }
    }

    public function down(): void
    {
        // Forward-only migration policy: do not edit or collapse historical workflow states.
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
