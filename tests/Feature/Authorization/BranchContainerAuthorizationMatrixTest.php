<?php

namespace Tests\Feature\Authorization;

use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\BuildsHighRiskDomainFixtures;
use Tests\TestCase;

class BranchContainerAuthorizationMatrixTest extends TestCase
{
    use BuildsHighRiskDomainFixtures;

    #[Test]
    public function same_tenant_user_with_permissions_can_view_and_update_branches_and_containers(): void
    {
        $this->requireTables([
            'accounts',
            'users',
            'companies',
            'branches',
            'branch_staff',
            'vessels',
            'vessel_schedules',
            'containers',
            'container_shipments',
            'shipments',
        ]);

        $account = $this->createTenantAccount();
        $actor = $this->createExternalUser((string) $account->id, [
            'branches.read',
            'branches.manage',
            'containers.read',
            'containers.manage',
        ]);
        $company = $this->createCompanyRecord((string) $account->id);
        $branch = $this->createBranchRecord((string) $account->id, (string) $company->id);
        $this->createBranchStaffRecord((string) $branch->id, (string) $actor->id);
        $shipment = $this->createShipmentRecord((string) $account->id, (string) $actor->id);
        $vessel = $this->createVesselRecord((string) $account->id);
        $schedule = $this->createVesselScheduleRecord((string) $account->id, (string) $vessel->id);
        $container = $this->createContainerRecord(
            (string) $account->id,
            (string) (Schema::hasColumn('containers', 'vessel_id') ? $vessel->id : $schedule->id),
            (string) $branch->id
        );
        $this->createContainerShipmentRecord((string) $container->id, (string) $shipment->id);

        Sanctum::actingAs($actor);

        $this->getJson('/api/v1/branches/' . $branch->id)->assertOk();
        $this->putJson('/api/v1/branches/' . $branch->id, [
            'name' => 'Updated Branch Name',
        ])->assertOk();

        $this->getJson('/api/v1/containers/' . $container->id)->assertOk();
        $this->putJson('/api/v1/containers/' . $container->id, [
            'status' => 'loading',
        ])->assertOk();
    }

    #[Test]
    public function same_tenant_user_without_permissions_gets_403_for_branches_and_containers(): void
    {
        $this->requireTables([
            'accounts',
            'users',
            'companies',
            'branches',
            'branch_staff',
            'vessels',
            'vessel_schedules',
            'containers',
            'container_shipments',
            'shipments',
        ]);

        $account = $this->createTenantAccount();
        $actor = $this->createExternalUser((string) $account->id);
        $company = $this->createCompanyRecord((string) $account->id);
        $branch = $this->createBranchRecord((string) $account->id, (string) $company->id);
        $this->createBranchStaffRecord((string) $branch->id, (string) $actor->id);
        $shipment = $this->createShipmentRecord((string) $account->id, (string) $actor->id);
        $vessel = $this->createVesselRecord((string) $account->id);
        $schedule = $this->createVesselScheduleRecord((string) $account->id, (string) $vessel->id);
        $container = $this->createContainerRecord(
            (string) $account->id,
            (string) (Schema::hasColumn('containers', 'vessel_id') ? $vessel->id : $schedule->id)
        );
        $this->createContainerShipmentRecord((string) $container->id, (string) $shipment->id);

        Sanctum::actingAs($actor);

        $this->getJson('/api/v1/branches/' . $branch->id)
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'ERR_PERMISSION');

        $this->putJson('/api/v1/containers/' . $container->id, [
            'status' => 'loading',
        ])->assertStatus(403)
            ->assertJsonPath('error_code', 'ERR_PERMISSION');
    }

    #[Test]
    public function cross_tenant_branch_and_container_ids_return_404_even_with_permissions(): void
    {
        $this->requireTables([
            'accounts',
            'users',
            'companies',
            'branches',
            'branch_staff',
            'vessels',
            'vessel_schedules',
            'containers',
            'container_shipments',
            'shipments',
        ]);

        $accountA = $this->createTenantAccount(['name' => 'Account A']);
        $accountB = $this->createTenantAccount(['name' => 'Account B']);

        $resourceOwner = $this->createExternalUser((string) $accountA->id);
        $actor = $this->createExternalUser((string) $accountB->id, [
            'branches.read',
            'branches.manage',
            'containers.read',
            'containers.manage',
        ]);

        $companyA = $this->createCompanyRecord((string) $accountA->id);
        $branchA = $this->createBranchRecord((string) $accountA->id, (string) $companyA->id);
        $this->createBranchStaffRecord((string) $branchA->id, (string) $resourceOwner->id);
        $shipmentA = $this->createShipmentRecord((string) $accountA->id, (string) $resourceOwner->id);
        $vesselA = $this->createVesselRecord((string) $accountA->id);
        $scheduleA = $this->createVesselScheduleRecord((string) $accountA->id, (string) $vesselA->id);
        $containerA = $this->createContainerRecord(
            (string) $accountA->id,
            (string) (Schema::hasColumn('containers', 'vessel_id') ? $vesselA->id : $scheduleA->id),
            (string) $branchA->id
        );
        $this->createContainerShipmentRecord((string) $containerA->id, (string) $shipmentA->id);

        Sanctum::actingAs($actor);

        $this->getJson('/api/v1/branches/' . $branchA->id)->assertNotFound();
        $this->getJson('/api/v1/containers/' . $containerA->id)->assertNotFound();
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
