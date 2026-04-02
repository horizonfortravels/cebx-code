<?php

namespace Tests\Feature\Web;

use App\Models\ContentDeclaration;
use App\Models\DgAuditLog;
use App\Models\Shipment;
use App\Models\User;
use Database\Seeders\E2EUserMatrixSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InternalComplianceActionsWebTest extends TestCase
{
    use RefreshDatabase;

    private ContentDeclaration $caseC;
    private ContentDeclaration $caseD;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(E2EUserMatrixSeeder::class);

        $this->caseC = $this->declarationByReference('SHP-I7A-C-001');
        $this->caseD = $this->declarationByReference('SHP-I7A-D-001');
    }

    #[Test]
    public function super_admin_can_mark_a_non_dg_case_as_requires_action_and_append_audit(): void
    {
        $superAdmin = $this->userByEmail('e2e.internal.super_admin@example.test');
        $reason = 'Customer must correct the declaration wording before the shipment can continue.';

        $this->actingAs($superAdmin, 'web')
            ->post(route('internal.compliance.requires-action', $this->caseD), [
                'reason' => $reason,
            ])
            ->assertRedirect(route('internal.compliance.show', $this->caseD))
            ->assertSessionHas('success');

        $this->caseD->refresh();

        $this->assertSame(ContentDeclaration::STATUS_REQUIRES_ACTION, (string) $this->caseD->status);
        $this->assertSame($reason, (string) $this->caseD->hold_reason);

        $shipment = Shipment::query()
            ->withoutGlobalScopes()
            ->findOrFail($this->caseD->shipment_id);

        $this->assertSame(Shipment::STATUS_REQUIRES_ACTION, (string) $shipment->status);
        $this->assertSame($reason, (string) $shipment->status_reason);

        $audit = DgAuditLog::query()
            ->withoutGlobalScopes()
            ->where('declaration_id', (string) $this->caseD->id)
            ->where('action', DgAuditLog::ACTION_STATUS_CHANGED)
            ->latest('created_at')
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame((string) $superAdmin->id, (string) $audit->actor_id);
        $this->assertSame('super_admin', (string) $audit->actor_role);
        $this->assertSame($reason, (string) $audit->notes);
        $this->assertSame(ContentDeclaration::STATUS_PENDING, (string) data_get($audit->old_values, 'status'));
        $this->assertSame(ContentDeclaration::STATUS_REQUIRES_ACTION, (string) data_get($audit->new_values, 'status'));
    }

    #[Test]
    public function super_admin_cannot_request_correction_for_a_dg_hold_case(): void
    {
        $superAdmin = $this->userByEmail('e2e.internal.super_admin@example.test');
        $originalStatus = (string) $this->caseC->status;
        $originalReason = (string) ($this->caseC->hold_reason ?? '');

        $this->actingAs($superAdmin, 'web')
            ->post(route('internal.compliance.requires-action', $this->caseC), [
                'reason' => 'Attempt to convert DG hold into a correction request.',
            ])
            ->assertRedirect(route('internal.compliance.show', $this->caseC))
            ->assertSessionHas('error');

        $this->caseC->refresh();

        $this->assertSame($originalStatus, (string) $this->caseC->status);
        $this->assertSame($originalReason, (string) ($this->caseC->hold_reason ?? ''));
    }

    #[Test]
    public function support_and_ops_readonly_cannot_mutate_internal_compliance_cases(): void
    {
        foreach ([
            'e2e.internal.support@example.test',
            'e2e.internal.ops_readonly@example.test',
        ] as $email) {
            $user = $this->userByEmail($email);

            $this->assertForbiddenInternalSurface(
                $this->actingAs($user, 'web')->post(route('internal.compliance.requires-action', $this->caseD), [
                    'reason' => 'Blocked mutation attempt.',
                ])
            );
        }
    }

    #[Test]
    public function carrier_manager_and_external_users_are_forbidden_from_internal_compliance_action_routes(): void
    {
        $carrierManager = $this->userByEmail('e2e.internal.carrier_manager@example.test');
        $externalUser = $this->userByEmail('e2e.c.organization_owner@example.test');

        $this->assertForbiddenInternalSurface(
            $this->actingAs($carrierManager, 'web')->post(route('internal.compliance.requires-action', $this->caseD), [
                'reason' => 'Blocked mutation attempt.',
            ])
        );

        $this->assertForbiddenInternalSurface(
            $this->actingAs($externalUser, 'web')->post(route('internal.compliance.requires-action', $this->caseD), [
                'reason' => 'Blocked mutation attempt.',
            ])
        );
    }

    #[Test]
    public function only_super_admin_sees_the_internal_compliance_action_panel(): void
    {
        $this->actingAs($this->userByEmail('e2e.internal.super_admin@example.test'), 'web')
            ->get(route('internal.compliance.show', $this->caseD))
            ->assertOk()
            ->assertSee('data-testid="internal-compliance-actions-card"', false)
            ->assertSee('data-testid="internal-compliance-requires-action-form"', false);

        foreach ([
            'e2e.internal.support@example.test',
            'e2e.internal.ops_readonly@example.test',
        ] as $email) {
            $this->actingAs($this->userByEmail($email), 'web')
                ->get(route('internal.compliance.show', $this->caseD))
                ->assertOk()
                ->assertDontSee('data-testid="internal-compliance-actions-card"', false)
                ->assertDontSee('data-testid="internal-compliance-requires-action-form"', false);
        }
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
            ->assertSeeText('403')
            ->assertDontSeeText('Internal Server Error');
    }
}
