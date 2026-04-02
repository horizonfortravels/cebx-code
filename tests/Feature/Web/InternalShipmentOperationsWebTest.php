<?php

namespace Tests\Feature\Web;

use App\Models\Shipment;
use App\Models\User;
use Database\Seeders\E2EUserMatrixSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InternalShipmentOperationsWebTest extends TestCase
{
    use RefreshDatabase;

    private Shipment $shipment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(E2EUserMatrixSeeder::class);

        $this->shipment = Shipment::query()
            ->withoutGlobalScopes()
            ->where('reference_number', 'SHP-I5A-D-001')
            ->firstOrFail();
    }

    #[Test]
    public function super_admin_and_support_can_see_safe_operational_actions_on_shipment_detail(): void
    {
        foreach ([
            'e2e.internal.super_admin@example.test',
            'e2e.internal.support@example.test',
        ] as $email) {
            $response = $this->actingAs($this->userByEmail($email), 'web')
                ->get(route('internal.shipments.show', $this->shipment))
                ->assertOk()
                ->assertSee('data-testid="internal-shipment-actions-card"', false)
                ->assertSee('data-testid="internal-shipment-refresh-link"', false)
                ->assertSee('data-testid="internal-shipment-documents-workspace-link"', false)
                ->assertSee('data-testid="internal-shipment-actions-account-link"', false)
                ->assertSee('data-testid="internal-shipment-actions-kyc-link"', false)
                ->assertSee('data-testid="internal-shipment-actions-public-tracking-link"', false)
                ->assertSee('data-testid="internal-shipment-copy-public-tracking-link"', false)
                ->assertSee('data-testid="internal-shipment-copy-status"', false)
                ->assertSee(route('internal.shipments.documents.index', $this->shipment), false)
                ->assertSee(route('internal.accounts.show', $this->shipment->account_id), false)
                ->assertSee(route('internal.kyc.show', $this->shipment->account_id), false)
                ->assertSee(route('public.tracking.show', ['token' => 'i5a-public-token-d-001']), false)
                ->assertDontSeeText('Retry carrier creation')
                ->assertDontSeeText('Cancel at carrier')
                ->assertDontSeeText('Manual status edit');

            $response->assertSee(
                'data-copy-text="' . e(route('public.tracking.show', ['token' => 'i5a-public-token-d-001'])) . '"',
                false
            );
        }
    }

    #[Test]
    public function ops_readonly_sees_read_only_operational_actions_without_account_access(): void
    {
        $this->actingAs($this->userByEmail('e2e.internal.ops_readonly@example.test'), 'web')
            ->get(route('internal.shipments.show', $this->shipment))
            ->assertOk()
            ->assertSee('data-testid="internal-shipment-actions-card"', false)
            ->assertSee('data-testid="internal-shipment-refresh-link"', false)
            ->assertSee('data-testid="internal-shipment-documents-workspace-link"', false)
            ->assertSee('data-testid="internal-shipment-actions-kyc-link"', false)
            ->assertSee('data-testid="internal-shipment-actions-public-tracking-link"', false)
            ->assertSee('data-testid="internal-shipment-copy-public-tracking-link"', false)
            ->assertDontSee('data-testid="internal-shipment-actions-account-link"', false)
            ->assertDontSee(route('internal.accounts.show', $this->shipment->account_id), false)
            ->assertSee(route('internal.kyc.show', $this->shipment->account_id), false)
            ->assertSee(route('internal.shipments.documents.index', $this->shipment), false);
    }

    #[Test]
    public function carrier_manager_is_denied_from_shipment_detail_but_can_use_canonical_documents_surface(): void
    {
        $carrierManager = $this->userByEmail('e2e.internal.carrier_manager@example.test');

        $this->assertForbiddenInternalSurface(
            $this->actingAs($carrierManager, 'web')->get(route('internal.shipments.show', $this->shipment))
        );

        $this->actingAs($carrierManager, 'web')
            ->get(route('internal.shipments.documents.index', $this->shipment))
            ->assertOk()
            ->assertSee('data-testid="internal-shipment-documents-workspace"', false)
            ->assertDontSee('href="' . route('internal.shipments.show', $this->shipment) . '"', false);
    }

    #[Test]
    public function external_users_are_forbidden_from_internal_shipment_operational_surfaces(): void
    {
        $externalUser = $this->userByEmail('e2e.c.organization_owner@example.test');

        $this->assertForbiddenInternalSurface(
            $this->actingAs($externalUser, 'web')->get(route('internal.shipments.show', $this->shipment))
        );

        $this->assertForbiddenInternalSurface(
            $this->actingAs($externalUser, 'web')->get(route('internal.shipments.documents.index', $this->shipment))
        );
    }

    private function userByEmail(string $email): User
    {
        return User::query()
            ->withoutGlobalScopes()
            ->where('email', $email)
            ->firstOrFail();
    }

    private function assertForbiddenInternalSurface(TestResponse $response): void
    {
        $response->assertForbidden()
            ->assertSee('class="panel"', false)
            ->assertDontSee('data-testid="internal-shipment-actions-card"', false);
    }
}
