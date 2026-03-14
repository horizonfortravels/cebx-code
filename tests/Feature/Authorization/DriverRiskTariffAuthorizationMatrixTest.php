<?php

namespace Tests\Feature\Authorization;

use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\BuildsHighRiskDomainFixtures;
use Tests\TestCase;

class DriverRiskTariffAuthorizationMatrixTest extends TestCase
{
    use BuildsHighRiskDomainFixtures;

    #[Test]
    public function same_tenant_user_with_permissions_can_access_driver_risk_and_tariff_endpoints(): void
    {
        $this->requireTables([
            'accounts',
            'users',
            'shipments',
            'drivers',
            'delivery_assignments',
            'risk_scores',
            'tariff_rules',
        ]);

        $account = $this->createTenantAccount();
        $actor = $this->createExternalUser((string) $account->id, [
            'drivers.read',
            'drivers.manage',
            'risk.read',
            'tariffs.read',
            'tariffs.manage',
        ]);
        $shipment = $this->createShipmentRecord((string) $account->id, (string) $actor->id);
        $this->createRiskScoreRecord((string) $shipment->id);
        $driver = $this->createDriverRecord((string) $account->id);
        $this->createDeliveryAssignmentRecord((string) $account->id, (string) $shipment->id, (string) $driver->id);
        $tariff = $this->createTariffRuleRecord((string) $account->id);

        Sanctum::actingAs($actor);

        $this->getJson('/api/v1/drivers/' . $driver->id)->assertOk();
        $this->putJson('/api/v1/drivers/' . $driver->id, [
            'name' => 'Updated Driver Name',
            'phone' => '+966512345678',
        ])->assertOk();

        $this->getJson('/api/v1/risk/shipments/' . $shipment->id)->assertOk();

        $this->getJson('/api/v1/tariffs/' . $tariff->id)->assertOk();
        $this->putJson('/api/v1/tariffs/' . $tariff->id, [
            'name' => 'Updated Tariff Name',
        ])->assertOk();
    }

    #[Test]
    public function same_tenant_user_without_permissions_gets_403_for_driver_risk_and_tariff_endpoints(): void
    {
        $this->requireTables([
            'accounts',
            'users',
            'shipments',
            'drivers',
            'delivery_assignments',
            'tariff_rules',
        ]);

        $account = $this->createTenantAccount();
        $actor = $this->createExternalUser((string) $account->id);
        $shipment = $this->createShipmentRecord((string) $account->id, (string) $actor->id);
        $this->createRiskScoreRecord((string) $shipment->id);
        $driver = $this->createDriverRecord((string) $account->id);
        $this->createDeliveryAssignmentRecord((string) $account->id, (string) $shipment->id, (string) $driver->id);
        $tariff = $this->createTariffRuleRecord((string) $account->id);

        Sanctum::actingAs($actor);

        $this->getJson('/api/v1/drivers/' . $driver->id)
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'ERR_PERMISSION');

        $this->getJson('/api/v1/risk/shipments/' . $shipment->id)
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'ERR_PERMISSION');

        $this->putJson('/api/v1/tariffs/' . $tariff->id, [
            'name' => 'Unauthorized Tariff Update',
        ])->assertStatus(403)
            ->assertJsonPath('error_code', 'ERR_PERMISSION');
    }

    #[Test]
    public function cross_tenant_driver_risk_and_tariff_ids_return_404_even_with_permissions(): void
    {
        $this->requireTables([
            'accounts',
            'users',
            'shipments',
            'drivers',
            'delivery_assignments',
            'risk_scores',
            'tariff_rules',
        ]);

        $accountA = $this->createTenantAccount(['name' => 'Account A']);
        $accountB = $this->createTenantAccount(['name' => 'Account B']);

        $resourceOwner = $this->createExternalUser((string) $accountA->id);
        $actor = $this->createExternalUser((string) $accountB->id, [
            'drivers.read',
            'drivers.manage',
            'risk.read',
            'tariffs.read',
            'tariffs.manage',
        ]);

        $shipmentA = $this->createShipmentRecord((string) $accountA->id, (string) $resourceOwner->id);
        $this->createRiskScoreRecord((string) $shipmentA->id);
        $driverA = $this->createDriverRecord((string) $accountA->id);
        $this->createDeliveryAssignmentRecord((string) $accountA->id, (string) $shipmentA->id, (string) $driverA->id);
        $tariffA = $this->createTariffRuleRecord((string) $accountA->id);

        Sanctum::actingAs($actor);

        $this->getJson('/api/v1/drivers/' . $driverA->id)->assertNotFound();
        $this->getJson('/api/v1/risk/shipments/' . $shipmentA->id)->assertNotFound();
        $this->getJson('/api/v1/tariffs/' . $tariffA->id)->assertNotFound();
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
