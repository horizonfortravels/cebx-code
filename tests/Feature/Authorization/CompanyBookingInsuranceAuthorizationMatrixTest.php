<?php

namespace Tests\Feature\Authorization;

use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\BuildsHighRiskDomainFixtures;
use Tests\TestCase;

class CompanyBookingInsuranceAuthorizationMatrixTest extends TestCase
{
    use BuildsHighRiskDomainFixtures;

    #[Test]
    public function same_tenant_user_with_permissions_can_access_company_booking_and_insurance_endpoints(): void
    {
        $this->requireTables(['accounts', 'users', 'companies', 'branches', 'shipments', 'shipment_charges']);

        $account = $this->createTenantAccount();
        $actor = $this->createExternalUser((string) $account->id, [
            'companies.read',
            'companies.manage',
            'booking.manage',
            'insurance.manage',
        ]);
        $company = $this->createCompanyRecord((string) $account->id);
        $this->createBranchRecord((string) $account->id, (string) $company->id);
        $shipment = $this->createShipmentRecord((string) $account->id, (string) $actor->id, [
            'declared_value' => 1200,
        ]);

        Sanctum::actingAs($actor);

        $this->getJson('/api/v1/companies/' . $company->id)->assertOk();
        $this->putJson('/api/v1/companies/' . $company->id, [
            'name' => 'Updated Company Name',
        ])->assertOk();

        $this->postJson('/api/v1/booking/' . $shipment->id . '/confirm')->assertOk();

        $this->postJson('/api/v1/insurance/quote', [
            'declared_value' => 1200,
            'shipment_type' => 'air',
            'destination_country' => 'SA',
        ])->assertOk();

        $this->postJson('/api/v1/insurance/' . $shipment->id . '/purchase', [
            'plan' => 'basic',
        ])->assertOk();
    }

    #[Test]
    public function same_tenant_user_without_permissions_gets_403_for_company_booking_and_insurance_endpoints(): void
    {
        $this->requireTables(['accounts', 'users', 'companies', 'branches', 'shipments']);

        $account = $this->createTenantAccount();
        $actor = $this->createExternalUser((string) $account->id);
        $company = $this->createCompanyRecord((string) $account->id);
        $this->createBranchRecord((string) $account->id, (string) $company->id);
        $shipment = $this->createShipmentRecord((string) $account->id, (string) $actor->id, [
            'declared_value' => 1200,
        ]);

        Sanctum::actingAs($actor);

        $this->getJson('/api/v1/companies/' . $company->id)
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'ERR_PERMISSION');

        $this->postJson('/api/v1/booking/' . $shipment->id . '/confirm')
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'ERR_PERMISSION');

        $this->postJson('/api/v1/insurance/' . $shipment->id . '/purchase', [
            'plan' => 'basic',
        ])->assertStatus(403)
            ->assertJsonPath('error_code', 'ERR_PERMISSION');
    }

    #[Test]
    public function cross_tenant_company_booking_and_insurance_resource_ids_return_404_even_with_permissions(): void
    {
        $this->requireTables(['accounts', 'users', 'companies', 'branches', 'shipments']);

        $accountA = $this->createTenantAccount(['name' => 'Account A']);
        $accountB = $this->createTenantAccount(['name' => 'Account B']);

        $owner = $this->createExternalUser((string) $accountA->id);
        $actor = $this->createExternalUser((string) $accountB->id, [
            'companies.read',
            'companies.manage',
            'booking.manage',
            'insurance.manage',
        ]);

        $companyA = $this->createCompanyRecord((string) $accountA->id);
        $this->createBranchRecord((string) $accountA->id, (string) $companyA->id);
        $shipmentA = $this->createShipmentRecord((string) $accountA->id, (string) $owner->id, [
            'declared_value' => 900,
            'status' => 'draft',
        ]);

        Sanctum::actingAs($actor);

        $this->postJson('/api/v1/booking/' . $shipmentA->id . '/confirm')->assertNotFound();
        $this->postJson('/api/v1/insurance/' . $shipmentA->id . '/purchase', [
            'plan' => 'basic',
        ])->assertNotFound();
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
