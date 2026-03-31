<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\ContentDeclaration;
use App\Models\DgAuditLog;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\User;
use App\Services\DgComplianceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DgAuditLogShipmentUuidNormalizationTest extends TestCase
{
    use RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_backfills_shipment_uuid_for_existing_logs_that_match_real_shipments(): void
    {
        $account = Account::factory()->create();
        $role = Role::factory()->create(['account_id' => $account->id]);
        $user = $this->createUserWithRole((string) $account->id, (string) $role->id);
        $shipment = Shipment::factory()->create([
            'account_id' => $account->id,
            'user_id' => $user->id,
            'created_by' => $user->id,
        ]);
        $declaration = ContentDeclaration::factory()->create([
            'account_id' => $account->id,
            'shipment_id' => $shipment->id,
            'declared_by' => $user->id,
        ]);

        DB::table('dg_audit_logs')->insert([
            'id' => (string) fake()->uuid(),
            'declaration_id' => $declaration->id,
            'shipment_id' => $shipment->id,
            'shipment_uuid' => null,
            'action' => DgAuditLog::ACTION_CREATED,
            'account_id' => $account->id,
            'actor_id' => $user->id,
            'payload' => json_encode(['source' => 'legacy'], JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration = require database_path('migrations/2026_03_31_000408_add_shipment_uuid_to_dg_audit_logs.php');
        $migration->up();

        $this->assertDatabaseHas('dg_audit_logs', [
            'declaration_id' => $declaration->id,
            'shipment_id' => $shipment->id,
            'shipment_uuid' => $shipment->id,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_dual_writes_shipment_uuid_for_real_shipments_and_keeps_legacy_string_ids_working(): void
    {
        $service = app(DgComplianceService::class);
        $account = Account::factory()->create();
        $role = Role::factory()->create(['account_id' => $account->id]);
        $user = $this->createUserWithRole((string) $account->id, (string) $role->id);
        $shipment = Shipment::factory()->create([
            'account_id' => $account->id,
            'user_id' => $user->id,
            'created_by' => $user->id,
        ]);

        $declaration = $service->createDeclaration((string) $account->id, (string) $shipment->id, (string) $user->id);

        $this->assertDatabaseHas('dg_audit_logs', [
            'declaration_id' => $declaration->id,
            'shipment_id' => $shipment->id,
            'shipment_uuid' => $shipment->id,
        ]);

        $legacyDeclaration = $service->createDeclaration((string) $account->id, 'SH-LEGACY-UUID-TEST', (string) $user->id);

        $this->assertDatabaseHas('dg_audit_logs', [
            'declaration_id' => $legacyDeclaration->id,
            'shipment_id' => 'SH-LEGACY-UUID-TEST',
            'shipment_uuid' => null,
        ]);

        if (Schema::hasColumn('dg_audit_logs', 'shipment_uuid')) {
            $this->assertSame(
                1,
                $service->getShipmentAuditLog((string) $shipment->id)->total()
            );
        }

        $this->assertSame(
            1,
            $service->getShipmentAuditLog('SH-LEGACY-UUID-TEST')->total()
        );
    }
}
