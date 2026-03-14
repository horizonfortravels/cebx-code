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

        $enumValues = $this->extractEnumValues($type);
        $statesToInsert = [
            'declaration_required' => 'offer_selected',
            'declaration_complete' => 'declaration_required',
            'requires_action' => 'declaration_complete',
        ];

        foreach ($statesToInsert as $state => $insertAfter) {
            if (in_array($state, $enumValues, true)) {
                continue;
            }

            $position = array_search($insertAfter, $enumValues, true);

            if ($position === false) {
                $enumValues[] = $state;
            } else {
                array_splice($enumValues, $position + 1, 0, [$state]);
            }
        }

        $enumValues = array_values(array_unique($enumValues));
        $enumList = implode(
            "','",
            array_map(static fn (string $value): string => str_replace("'", "''", $value), $enumValues)
        );

        DB::statement("ALTER TABLE `shipments` MODIFY COLUMN `status` ENUM('{$enumList}') DEFAULT 'draft'");

        if (Schema::hasColumn('shipments', 'status_reason')) {
            DB::table('shipments')
                ->where('status', 'offer_selected')
                ->update([
                    'status' => 'declaration_required',
                    'status_reason' => DB::raw("COALESCE(status_reason, 'Dangerous goods declaration must be completed before payment or issuance.')"),
                ]);
        } else {
            DB::table('shipments')
                ->where('status', 'offer_selected')
                ->update([
                    'status' => 'declaration_required',
                ]);
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
