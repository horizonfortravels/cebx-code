<?php

namespace Tests\Feature\Authorization;

use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\BuildsHighRiskDomainFixtures;
use Tests\TestCase;

class VesselWorkflowSlaAuthorizationMatrixTest extends TestCase
{
    use BuildsHighRiskDomainFixtures;

    #[Test]
    public function same_tenant_user_with_permissions_can_access_vessel_schedule_workflow_and_sla_endpoints(): void
    {
        $this->requireTables(['accounts', 'users', 'vessels', 'vessel_schedules', 'shipments']);

        $account = $this->createTenantAccount();
        $actor = $this->createExternalUser((string) $account->id, [
            'vessels.read',
            'vessels.manage',
            'vessel_schedules.read',
            'vessel_schedules.manage',
            'shipment_workflow.read',
            'sla.read',
        ]);
        $vessel = $this->createVesselRecord((string) $account->id);
        $schedule = $this->createVesselScheduleRecord((string) $account->id, (string) $vessel->id);
        $shipment = $this->createShipmentRecord((string) $account->id, (string) $actor->id, [
            'status' => 'draft',
            'service_level' => 'standard',
        ]);

        Sanctum::actingAs($actor);

        $this->getJson('/api/v1/vessels/' . $vessel->id)->assertOk();
        $this->putJson('/api/v1/vessel-schedules/' . $schedule->id, [
            'status' => 'scheduled',
        ])->assertOk();
        $this->getJson('/api/v1/shipment-workflow/' . $shipment->id . '/next-statuses')->assertOk();
        $this->getJson('/api/v1/sla/check/' . $shipment->id)->assertOk();
    }

    #[Test]
    public function same_tenant_user_without_permissions_gets_403_for_vessel_workflow_and_sla_endpoints(): void
    {
        $this->requireTables(['accounts', 'users', 'vessels', 'vessel_schedules', 'shipments']);

        $account = $this->createTenantAccount();
        $actor = $this->createExternalUser((string) $account->id);
        $vessel = $this->createVesselRecord((string) $account->id);
        $schedule = $this->createVesselScheduleRecord((string) $account->id, (string) $vessel->id);
        $shipment = $this->createShipmentRecord((string) $account->id, (string) $actor->id);

        Sanctum::actingAs($actor);

        $this->getJson('/api/v1/vessels/' . $vessel->id)
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'ERR_PERMISSION');

        $this->putJson('/api/v1/vessel-schedules/' . $schedule->id, [
            'status' => 'scheduled',
        ])->assertStatus(403)
            ->assertJsonPath('error_code', 'ERR_PERMISSION');

        $this->getJson('/api/v1/shipment-workflow/' . $shipment->id . '/next-statuses')
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'ERR_PERMISSION');

        $this->getJson('/api/v1/sla/check/' . $shipment->id)
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'ERR_PERMISSION');
    }

    #[Test]
    public function cross_tenant_vessel_schedule_workflow_and_sla_resource_ids_return_404_even_with_permissions(): void
    {
        $this->requireTables(['accounts', 'users', 'vessels', 'vessel_schedules', 'shipments']);

        $accountA = $this->createTenantAccount(['name' => 'Account A']);
        $accountB = $this->createTenantAccount(['name' => 'Account B']);

        $owner = $this->createExternalUser((string) $accountA->id);
        $actor = $this->createExternalUser((string) $accountB->id, [
            'vessels.read',
            'vessels.manage',
            'vessel_schedules.read',
            'vessel_schedules.manage',
            'shipment_workflow.read',
            'sla.read',
        ]);

        $vesselA = $this->createVesselRecord((string) $accountA->id);
        $scheduleA = $this->createVesselScheduleRecord((string) $accountA->id, (string) $vesselA->id);
        $shipmentA = $this->createShipmentRecord((string) $accountA->id, (string) $owner->id);

        Sanctum::actingAs($actor);

        $this->getJson('/api/v1/vessel-schedules/' . $scheduleA->id)->assertNotFound();
        $this->getJson('/api/v1/shipment-workflow/' . $shipmentA->id . '/next-statuses')->assertNotFound();
        $this->getJson('/api/v1/sla/check/' . $shipmentA->id)->assertNotFound();
    }

    /**
     * @param array<int, string> $tables
     */
    private function requireTables(array $tables): void
    {
        foreach ($tables as $table) {
            if (!Schema::hasTable($table)) {
                $this->markTestSkipped(sprintf('Required table [%s] is not available in this environment.', $table));
            }
        }
    }
}
