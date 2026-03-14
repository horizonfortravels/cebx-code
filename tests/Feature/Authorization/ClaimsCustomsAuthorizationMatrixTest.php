<?php

namespace Tests\Feature\Authorization;

use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\BuildsHighRiskDomainFixtures;
use Tests\TestCase;

class ClaimsCustomsAuthorizationMatrixTest extends TestCase
{
    use BuildsHighRiskDomainFixtures;

    #[Test]
    public function same_tenant_user_with_permissions_can_view_and_update_claims_and_customs_declarations(): void
    {
        $this->requireTables([
            'accounts',
            'users',
            'shipments',
            'claims',
            'customs_brokers',
            'customs_declarations',
        ]);

        $account = $this->createTenantAccount();
        $actor = $this->createExternalUser((string) $account->id, [
            'claims.read',
            'claims.manage',
            'customs.read',
            'customs.manage',
        ]);
        $shipment = $this->createShipmentRecord((string) $account->id, (string) $actor->id);
        $claim = $this->createClaimRecord((string) $account->id, (string) $shipment->id);
        $broker = $this->createCustomsBrokerRecord((string) $account->id);
        $declaration = $this->createCustomsDeclarationRecord(
            (string) $account->id,
            (string) $shipment->id,
            (string) $broker->id
        );

        Sanctum::actingAs($actor);

        $this->getJson('/api/v1/claims/' . $claim->id)->assertOk();
        $this->putJson('/api/v1/claims/' . $claim->id, [
            'description' => 'Updated claim description for authorization matrix coverage.',
        ])->assertOk();

        $this->getJson('/api/v1/customs/declarations/' . $declaration->id)->assertOk();
        $this->putJson('/api/v1/customs/declarations/' . $declaration->id, [
            'declared_value' => 1400,
        ])->assertOk();
    }

    #[Test]
    public function same_tenant_user_without_permissions_gets_403_for_claims_and_customs(): void
    {
        $this->requireTables([
            'accounts',
            'users',
            'shipments',
            'claims',
            'customs_declarations',
        ]);

        $account = $this->createTenantAccount();
        $actor = $this->createExternalUser((string) $account->id);
        $shipment = $this->createShipmentRecord((string) $account->id, (string) $actor->id);
        $claim = $this->createClaimRecord((string) $account->id, (string) $shipment->id);
        $declaration = $this->createCustomsDeclarationRecord((string) $account->id, (string) $shipment->id);

        Sanctum::actingAs($actor);

        $this->getJson('/api/v1/claims/' . $claim->id)
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'ERR_PERMISSION');

        $this->putJson('/api/v1/customs/declarations/' . $declaration->id, [
            'declared_value' => 1300,
        ])->assertStatus(403)
            ->assertJsonPath('error_code', 'ERR_PERMISSION');
    }

    #[Test]
    public function cross_tenant_claim_and_customs_ids_return_404_even_with_permissions(): void
    {
        $this->requireTables([
            'accounts',
            'users',
            'shipments',
            'claims',
            'customs_declarations',
        ]);

        $accountA = $this->createTenantAccount(['name' => 'Account A']);
        $accountB = $this->createTenantAccount(['name' => 'Account B']);

        $resourceOwner = $this->createExternalUser((string) $accountA->id);
        $actor = $this->createExternalUser((string) $accountB->id, [
            'claims.read',
            'claims.manage',
            'customs.read',
            'customs.manage',
        ]);

        $shipmentA = $this->createShipmentRecord((string) $accountA->id, (string) $resourceOwner->id);
        $claimA = $this->createClaimRecord((string) $accountA->id, (string) $shipmentA->id);
        $declarationA = $this->createCustomsDeclarationRecord((string) $accountA->id, (string) $shipmentA->id);

        Sanctum::actingAs($actor);

        $this->getJson('/api/v1/claims/' . $claimA->id)->assertNotFound();
        $this->getJson('/api/v1/customs/declarations/' . $declarationA->id)->assertNotFound();
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
