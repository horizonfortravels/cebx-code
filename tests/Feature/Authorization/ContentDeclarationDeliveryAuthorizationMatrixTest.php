<?php

namespace Tests\Feature\Authorization;

use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\BuildsHighRiskDomainFixtures;
use Tests\TestCase;

class ContentDeclarationDeliveryAuthorizationMatrixTest extends TestCase
{
    use BuildsHighRiskDomainFixtures;

    #[Test]
    public function same_tenant_user_with_permissions_can_access_content_declarations_and_delivery_endpoints(): void
    {
        $this->requireTables(['accounts', 'users', 'shipments', 'content_declarations', 'drivers', 'delivery_assignments']);

        $account = $this->createTenantAccount();
        $actor = $this->createExternalUser((string) $account->id, [
            'content_declarations.read',
            'content_declarations.manage',
            'delivery.read',
        ]);
        $shipment = $this->createShipmentRecord((string) $account->id, (string) $actor->id);
        $declaration = $this->createContentDeclarationRecord((string) $account->id, (string) $shipment->id, (string) $actor->id);
        $driver = $this->createDriverRecord((string) $account->id);
        $this->createDeliveryAssignmentRecord((string) $account->id, (string) $shipment->id, (string) $driver->id);

        Sanctum::actingAs($actor);

        $this->getJson('/api/v1/content-declarations/' . $declaration->id)->assertOk();
        $this->putJson('/api/v1/content-declarations/' . $declaration->id, [
            'contains_dangerous_goods' => false,
        ])->assertOk();
        $this->getJson('/api/v1/delivery/driver/' . $driver->id . '/assignments')->assertOk();
    }

    #[Test]
    public function same_tenant_user_without_permissions_gets_403_for_content_declarations_and_delivery_endpoints(): void
    {
        $this->requireTables(['accounts', 'users', 'shipments', 'content_declarations', 'drivers', 'delivery_assignments']);

        $account = $this->createTenantAccount();
        $actor = $this->createExternalUser((string) $account->id);
        $shipment = $this->createShipmentRecord((string) $account->id, (string) $actor->id);
        $declaration = $this->createContentDeclarationRecord((string) $account->id, (string) $shipment->id, (string) $actor->id);
        $driver = $this->createDriverRecord((string) $account->id);
        $this->createDeliveryAssignmentRecord((string) $account->id, (string) $shipment->id, (string) $driver->id);

        Sanctum::actingAs($actor);

        $this->getJson('/api/v1/content-declarations/' . $declaration->id)
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'ERR_PERMISSION');

        $this->putJson('/api/v1/content-declarations/' . $declaration->id, [
            'contains_dangerous_goods' => false,
        ])->assertStatus(403)
            ->assertJsonPath('error_code', 'ERR_PERMISSION');

        $this->getJson('/api/v1/delivery/driver/' . $driver->id . '/assignments')
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'ERR_PERMISSION');
    }

    #[Test]
    public function cross_tenant_content_declaration_and_delivery_resource_ids_return_404_even_with_permissions(): void
    {
        $this->requireTables(['accounts', 'users', 'shipments', 'content_declarations', 'drivers', 'delivery_assignments']);

        $accountA = $this->createTenantAccount(['name' => 'Account A']);
        $accountB = $this->createTenantAccount(['name' => 'Account B']);

        $owner = $this->createExternalUser((string) $accountA->id);
        $actor = $this->createExternalUser((string) $accountB->id, [
            'content_declarations.read',
            'content_declarations.manage',
            'delivery.read',
        ]);

        $shipmentA = $this->createShipmentRecord((string) $accountA->id, (string) $owner->id);
        $declarationA = $this->createContentDeclarationRecord((string) $accountA->id, (string) $shipmentA->id, (string) $owner->id);
        $driverA = $this->createDriverRecord((string) $accountA->id);
        $this->createDeliveryAssignmentRecord((string) $accountA->id, (string) $shipmentA->id, (string) $driverA->id);

        Sanctum::actingAs($actor);

        $this->getJson('/api/v1/content-declarations/' . $declarationA->id)->assertNotFound();
        $this->getJson('/api/v1/delivery/driver/' . $driverA->id . '/assignments')->assertNotFound();
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
