<?php

namespace Tests\Feature\Web;

use App\Models\ContentDeclaration;
use App\Models\Shipment;
use App\Models\User;
use App\Models\WalletHold;
use Database\Seeders\E2EUserMatrixSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InternalComplianceReadCenterWebTest extends TestCase
{
    use RefreshDatabase;

    private ContentDeclaration $caseA;
    private ContentDeclaration $caseC;
    private ContentDeclaration $caseD;
    private Shipment $shipmentA;
    private Shipment $shipmentC;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(E2EUserMatrixSeeder::class);

        $this->caseA = $this->declarationByReference('SHP-I7A-A-001');
        $this->caseC = $this->declarationByReference('SHP-I7A-C-001');
        $this->caseD = $this->declarationByReference('SHP-I7A-D-001');
        $this->shipmentA = $this->shipmentByReference('SHP-I7A-A-001');
        $this->shipmentC = $this->shipmentByReference('SHP-I7A-C-001');
    }

    #[Test]
    public function super_admin_support_and_ops_readonly_can_open_compliance_index_and_detail(): void
    {
        foreach ([
            'e2e.internal.super_admin@example.test',
            'e2e.internal.support@example.test',
            'e2e.internal.ops_readonly@example.test',
        ] as $email) {
            $user = $this->userByEmail($email);

            $index = $this->actingAs($user, 'web')
                ->get(route('internal.compliance.index'))
                ->assertOk()
                ->assertSee('data-testid="internal-compliance-table"', false)
                ->assertSeeText('SHP-I7A-A-001')
                ->assertSeeText('SHP-I7A-C-001')
                ->assertSeeText('SHP-I7A-D-001');

            $this->assertHasNavigationLink($index, 'internal.compliance.index');

            $detail = $this->actingAs($user, 'web')
                ->get(route('internal.compliance.show', $this->caseC))
                ->assertOk()
                ->assertSee('data-testid="internal-compliance-case-summary-card"', false)
                ->assertSee('data-testid="internal-compliance-shipment-card"', false)
                ->assertSee('data-testid="internal-compliance-account-card"', false)
                ->assertSee('data-testid="internal-compliance-legal-card"', false)
                ->assertSee('data-testid="internal-compliance-workflow-card"', false)
                ->assertSee('data-testid="internal-compliance-notes-card"', false)
                ->assertSee('data-testid="internal-compliance-effects-card"', false)
                ->assertSee('data-testid="internal-compliance-dg-card"', false)
                ->assertSee('data-testid="internal-compliance-audit-card"', false)
                ->assertSee('data-testid="internal-compliance-audit-entry"', false)
                ->assertSee('data-testid="internal-compliance-audit-change-summary"', false)
                ->assertSeeText('SHP-I7A-C-001')
                ->assertSeeText('E2E Account C')
                ->assertSeeText('Manual dangerous-goods review')
                ->assertSeeText('UN1993')
                ->assertSeeText('Flammable liquid, n.o.s.')
                ->assertSeeText('Dangerous goods were declared, so the shipment remains in manual review until an internal team resolves the hold.')
                ->assertSeeText('Shipment workflow note')
                ->assertSeeText('Change summary: DG metadata: UN1993 / 3 / II')
                ->assertDontSeeText('i7a-hidden-waiver-hash-a')
                ->assertDontSeeText('I7A hidden waiver text snapshot A')
                ->assertDontSeeText('I7A hidden dg additional info C')
                ->assertDontSeeText('I7A hidden user agent C')
                ->assertDontSeeText('I7A hidden audit payload C');

            $detail->assertSee(route('internal.shipments.show', $this->shipmentC), false);
            $detail->assertSee(route('internal.kyc.show', $this->shipmentC->account), false);
            $detail->assertSee(route('internal.billing.show', $this->shipmentC->account), false);
            $detail->assertSee('data-testid="internal-compliance-kyc-link"', false)
                ->assertSee('data-testid="internal-compliance-billing-link"', false)
                ->assertDontSee('data-testid="internal-compliance-preflight-link"', false);

            if ($email === 'e2e.internal.ops_readonly@example.test') {
                $detail->assertDontSee('data-testid="internal-compliance-account-link"', false);
            } else {
                $detail->assertSee('data-testid="internal-compliance-account-link"', false);
            }
        }
    }

    #[Test]
    public function support_and_ops_readonly_can_follow_linked_kyc_billing_and_preflight_context_where_available(): void
    {
        $linkedHold = WalletHold::query()
            ->withoutGlobalScopes()
            ->where('account_id', (string) $this->shipmentA->account_id)
            ->where('status', WalletHold::STATUS_ACTIVE)
            ->orderBy('created_at')
            ->firstOrFail();

        $linkedHold->forceFill([
            'shipment_id' => (string) $this->shipmentA->id,
        ])->save();

        $this->shipmentA->forceFill([
            'balance_reservation_id' => (string) $linkedHold->id,
        ])->save();
        $this->shipmentA->refresh();

        foreach ([
            'e2e.internal.support@example.test',
            'e2e.internal.ops_readonly@example.test',
        ] as $email) {
            $user = $this->userByEmail($email);

            $detailWithHold = $this->actingAs($user, 'web')
                ->get(route('internal.compliance.show', $this->caseA))
                ->assertOk()
                ->assertSee('data-testid="internal-compliance-shipment-link"', false)
                ->assertSee('data-testid="internal-compliance-kyc-link"', false)
                ->assertSee('data-testid="internal-compliance-billing-link"', false)
                ->assertSee('data-testid="internal-compliance-preflight-link"', false)
                ->assertSee(route('internal.billing.preflights.show', [
                    'account' => $this->shipmentA->account,
                    'hold' => $linkedHold,
                ]), false);

            if ($email === 'e2e.internal.ops_readonly@example.test') {
                $detailWithHold->assertDontSee('data-testid="internal-compliance-account-link"', false);
            } else {
                $detailWithHold->assertSee('data-testid="internal-compliance-account-link"', false);
            }

            $this->actingAs($user, 'web')
                ->get(route('internal.compliance.show', $this->caseC))
                ->assertOk()
                ->assertDontSee('data-testid="internal-compliance-preflight-link"', false);
        }
    }

    #[Test]
    public function compliance_index_supports_search_and_basic_filters(): void
    {
        $viewer = $this->userByEmail('e2e.internal.super_admin@example.test');

        $this->actingAs($viewer, 'web')
            ->get(route('internal.compliance.index', ['q' => 'SHP-I7A-C-001']))
            ->assertOk()
            ->assertSeeText('SHP-I7A-C-001')
            ->assertDontSeeText('SHP-I7A-A-001');

        $this->actingAs($viewer, 'web')
            ->get(route('internal.compliance.index', ['type' => 'individual']))
            ->assertOk()
            ->assertSeeText('SHP-I7A-A-001')
            ->assertDontSeeText('SHP-I7A-C-001');

        $this->actingAs($viewer, 'web')
            ->get(route('internal.compliance.index', ['status' => ContentDeclaration::STATUS_COMPLETED]))
            ->assertOk()
            ->assertSeeText('SHP-I7A-A-001')
            ->assertDontSeeText('SHP-I7A-C-001');

        $this->actingAs($viewer, 'web')
            ->get(route('internal.compliance.index', ['review' => 'attention']))
            ->assertOk()
            ->assertSeeText('SHP-I7A-C-001')
            ->assertDontSeeText('SHP-I7A-A-001');
    }

    #[Test]
    public function compliance_detail_shows_legal_acknowledgement_and_audit_visibility(): void
    {
        $viewer = $this->userByEmail('e2e.internal.super_admin@example.test');

        $this->actingAs($viewer, 'web')
            ->get(route('internal.compliance.show', $this->caseA))
            ->assertOk()
            ->assertSee('data-testid="internal-compliance-legal-card"', false)
            ->assertSee('data-testid="internal-compliance-audit-card"', false)
            ->assertSeeText('Accepted')
            ->assertSeeText('Declaration completed')
            ->assertSeeText('Legal acknowledgement accepted')
            ->assertSeeText('Change summary: Waiver version: I7A-EN-1')
            ->assertDontSeeText('i7a-hidden-waiver-hash-a')
            ->assertDontSeeText('I7A hidden waiver text snapshot A');

        $this->actingAs($viewer, 'web')
            ->get(route('internal.compliance.show', $this->caseD))
            ->assertOk()
            ->assertSee('data-testid="internal-compliance-workflow-card"', false)
            ->assertSee('data-testid="internal-compliance-legal-card"', false)
            ->assertSeeText('Pending')
            ->assertSeeText('Accept the legal acknowledgement for this non-DG declaration before the shipment can proceed normally.')
            ->assertDontSeeText('I7A hidden user agent D');
    }

    #[Test]
    public function super_admin_support_and_ops_readonly_see_compliance_navigation_while_carrier_manager_does_not(): void
    {
        $superAdminPage = $this->actingAs($this->userByEmail('e2e.internal.super_admin@example.test'), 'web')
            ->get(route('admin.index'))
            ->assertOk();
        $this->assertHasNavigationLink($superAdminPage, 'internal.compliance.index');

        foreach ([
            'e2e.internal.support@example.test',
            'e2e.internal.ops_readonly@example.test',
        ] as $email) {
            $page = $this->actingAs($this->userByEmail($email), 'web')
                ->get(route('internal.home'))
                ->assertOk();

            $this->assertHasNavigationLink($page, 'internal.compliance.index');
        }

        $carrierManagerPage = $this->actingAs($this->userByEmail('e2e.internal.carrier_manager@example.test'), 'web')
            ->get(route('internal.home'))
            ->assertOk();

        $this->assertMissingNavigationLink($carrierManagerPage, 'internal.compliance.index');
    }

    #[Test]
    public function carrier_manager_is_forbidden_from_internal_compliance_routes(): void
    {
        $carrierManager = $this->userByEmail('e2e.internal.carrier_manager@example.test');

        $this->assertForbiddenInternalSurface(
            $this->actingAs($carrierManager, 'web')->get(route('internal.compliance.index'))
        );

        $this->assertForbiddenInternalSurface(
            $this->actingAs($carrierManager, 'web')->get(route('internal.compliance.show', $this->caseC))
        );
    }

    #[Test]
    public function external_users_are_forbidden_from_internal_compliance_routes(): void
    {
        $externalUser = $this->userByEmail('e2e.c.organization_owner@example.test');

        $this->assertForbiddenInternalSurface(
            $this->actingAs($externalUser, 'web')->get(route('internal.compliance.index'))
        );

        $this->assertForbiddenInternalSurface(
            $this->actingAs($externalUser, 'web')->get(route('internal.compliance.show', $this->caseC))
        );
    }

    private function declarationByReference(string $reference): ContentDeclaration
    {
        return ContentDeclaration::query()
            ->withoutGlobalScopes()
            ->whereHas('shipment', function ($query) use ($reference): void {
                $query->withoutGlobalScopes()->where('reference_number', $reference);
            })
            ->firstOrFail();
    }

    private function shipmentByReference(string $reference): Shipment
    {
        return Shipment::query()
            ->withoutGlobalScopes()
            ->where('reference_number', $reference)
            ->firstOrFail();
    }

    private function userByEmail(string $email): User
    {
        return User::query()
            ->withoutGlobalScopes()
            ->where('email', $email)
            ->firstOrFail();
    }

    private function assertHasNavigationLink(TestResponse $response, string $routeName): void
    {
        $response->assertSee('href="' . route($routeName) . '"', false);
    }

    private function assertMissingNavigationLink(TestResponse $response, string $routeName): void
    {
        $response->assertDontSee('href="' . route($routeName) . '"', false);
    }

    private function assertForbiddenInternalSurface(TestResponse $response): void
    {
        $response->assertForbidden()
            ->assertSee('class="panel"', false)
            ->assertSeeText('403')
            ->assertDontSeeText('Internal Server Error');
    }
}
