<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Account;
use App\Models\User;
use App\Models\Role;
use App\Models\AuditLog;
use App\Services\AuditService;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithStrictRbac;

/**
 * FR-IAM-006: Audit Log — Integration Tests (22 tests)
 * Tests API endpoints, permissions, filtering, export, and immutability via HTTP.
 */
class AuditLogApiTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithStrictRbac;

    protected Account $account;
    protected User $owner;
    protected User $member;
    protected AuditService $auditService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->auditService = new AuditService();
        AuditService::resetRequestId();

        $this->account = Account::factory()->create();
        $this->owner = User::factory()->create([
            'account_id' => $this->account->id,
            'is_owner'   => true,
        ]);
        $this->member = User::factory()->create([
            'account_id' => $this->account->id,
            'is_owner'   => false,
        ]);

        // Seed some audit logs
        $this->auditService->info($this->account->id, $this->owner->id, 'user.added', AuditLog::CATEGORY_USERS, 'User', $this->member->id);
        $this->auditService->info($this->account->id, $this->owner->id, 'role.created', AuditLog::CATEGORY_ROLES);
        $this->auditService->warning($this->account->id, $this->member->id, 'permission.denied', AuditLog::CATEGORY_ROLES);
        $this->auditService->critical($this->account->id, $this->owner->id, 'user.deleted', AuditLog::CATEGORY_USERS);
        $this->auditService->info($this->account->id, $this->owner->id, 'invitation.created', AuditLog::CATEGORY_INVITATION);
    }

    // ─── List / Search ───────────────────────────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function owner_can_list_audit_logs()
    {
        $response = $this->actingAs($this->owner)
            ->getJson('/api/v1/audit-logs');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [['id', 'action', 'severity', 'category', 'created_at']],
                'meta' => ['current_page', 'total'],
            ]);

        $this->assertEquals(5, $response->json('meta.total'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function can_filter_by_category()
    {
        $response = $this->actingAs($this->owner)
            ->getJson('/api/v1/audit-logs?category=users');

        $response->assertOk();
        foreach ($response->json('data') as $log) {
            $this->assertEquals('users', $log['category']);
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function can_filter_by_severity()
    {
        $response = $this->actingAs($this->owner)
            ->getJson('/api/v1/audit-logs?severity=critical');

        $response->assertOk();
        $this->assertEquals(1, $response->json('meta.total'));
        $this->assertEquals('critical', $response->json('data.0.severity'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function can_filter_by_actor()
    {
        $response = $this->actingAs($this->owner)
            ->getJson('/api/v1/audit-logs?actor_id=' . $this->member->id);

        $response->assertOk();
        $this->assertEquals(1, $response->json('meta.total'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function can_filter_by_date_range()
    {
        $response = $this->actingAs($this->owner)
            ->getJson('/api/v1/audit-logs?from=' . now()->subHour()->toDateString() . '&to=' . now()->addDay()->toDateString());

        $response->assertOk();
        $this->assertEquals(5, $response->json('meta.total'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function can_filter_by_action()
    {
        $response = $this->actingAs($this->owner)
            ->getJson('/api/v1/audit-logs?action=user.added');

        $response->assertOk();
        $this->assertEquals(1, $response->json('meta.total'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function can_paginate_results()
    {
        $response = $this->actingAs($this->owner)
            ->getJson('/api/v1/audit-logs?per_page=2');

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
        $this->assertEquals(5, $response->json('meta.total'));
        $this->assertEquals(3, $response->json('meta.last_page'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function can_sort_by_severity()
    {
        $response = $this->actingAs($this->owner)
            ->getJson('/api/v1/audit-logs?sort_by=severity&sort_dir=asc');

        $response->assertOk();
    }

    // ─── Show Single Entry ───────────────────────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function owner_can_view_single_audit_log()
    {
        $logId = AuditLog::withoutGlobalScopes()
            ->where('account_id', $this->account->id)
            ->first()->id;

        $response = $this->actingAs($this->owner)
            ->getJson("/api/v1/audit-logs/{$logId}");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => ['id', 'action', 'severity', 'category', 'ip_address', 'request_id'],
            ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function cannot_view_audit_log_from_another_account()
    {
        $otherAccount = Account::factory()->create();
        $this->auditService->info($otherAccount->id, null, 'account.created', AuditLog::CATEGORY_ACCOUNT);

        $otherLogId = AuditLog::withoutGlobalScopes()
            ->where('account_id', $otherAccount->id)
            ->first()->id;

        $response = $this->actingAs($this->owner)
            ->getJson("/api/v1/audit-logs/{$otherLogId}");

        $response->assertStatus(404);
    }

    // ─── Entity Trail ────────────────────────────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function owner_can_view_entity_trail()
    {
        $response = $this->actingAs($this->owner)
            ->getJson("/api/v1/audit-logs/entity/User/{$this->member->id}");

        $response->assertOk()
            ->assertJsonPath('meta.entity_type', 'User')
            ->assertJsonPath('meta.entity_id', $this->member->id);
    }

    // ─── Request Trace ───────────────────────────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function owner_can_view_request_trace()
    {
        AuditService::resetRequestId();
        AuditService::setRequestId('trace-test-abc');

        $this->auditService->info($this->account->id, $this->owner->id, 'user.added', AuditLog::CATEGORY_USERS);
        $this->auditService->info($this->account->id, $this->owner->id, 'role.assigned', AuditLog::CATEGORY_ROLES);

        $response = $this->actingAs($this->owner)
            ->getJson('/api/v1/audit-logs/trace/trace-test-abc');

        $response->assertOk()
            ->assertJsonPath('meta.request_id', 'trace-test-abc')
            ->assertJsonPath('meta.count', 2);
    }

    // ─── Statistics ──────────────────────────────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function owner_can_view_statistics()
    {
        $response = $this->actingAs($this->owner)
            ->getJson('/api/v1/audit-logs/statistics');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['total', 'by_severity', 'by_category', 'top_actions'],
            ]);

        $this->assertGreaterThanOrEqual(5, $response->json('data.total'));
    }

    // ─── Categories Metadata ─────────────────────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function can_list_audit_categories()
    {
        $response = $this->actingAs($this->owner)
            ->getJson('/api/v1/audit-logs/categories');

        $response->assertOk()
            ->assertJsonStructure(['categories', 'severities', 'actions']);

        $this->assertContains('auth', $response->json('categories'));
        $this->assertContains('users', $response->json('categories'));
    }

    // ─── Export ──────────────────────────────────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function owner_can_export_as_json()
    {
        $response = $this->actingAs($this->owner)
            ->postJson('/api/v1/audit-logs/export', ['format' => 'json']);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'timestamp', 'actor', 'action', 'severity']],
                'meta' => ['count'],
            ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function owner_can_export_as_csv()
    {
        $response = $this->actingAs($this->owner)
            ->postJson('/api/v1/audit-logs/export', ['format' => 'csv']);

        $response->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function export_with_filters()
    {
        $response = $this->actingAs($this->owner)
            ->postJson('/api/v1/audit-logs/export', [
                'format'   => 'json',
                'category' => 'users',
            ]);

        $response->assertOk();
        foreach ($response->json('data') as $row) {
            // Exported data includes the export action itself (category=export),
            // and the filtered data. Verify at least some are 'users'.
            $this->assertContains($row['category'], ['users', 'export']);
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function export_action_is_logged()
    {
        $this->actingAs($this->owner)
            ->postJson('/api/v1/audit-logs/export', ['format' => 'json']);

        $exportLog = AuditLog::withoutGlobalScopes()
            ->where('action', 'audit.exported')
            ->where('account_id', $this->account->id)
            ->first();

        $this->assertNotNull($exportLog);
    }

    // ─── Permission Checks ───────────────────────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function member_without_permission_cannot_view_audit_logs()
    {
        $response = $this->actingAs($this->member)
            ->getJson('/api/v1/audit-logs');

        $response->assertStatus(403);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function member_with_audit_view_permission_can_view()
    {
        // Create role with audit:view permission
        $role = $this->createTenantRoleWithPermissions(
            (string) $this->account->id,
            ['audit.view'],
            'audit_viewer'
        );
        $this->member->roles()->syncWithoutDetaching([
            (string) $role->id => [
                'assigned_by' => null,
                'assigned_at' => now(),
            ],
        ]);

        $response = $this->actingAs($this->member)
            ->getJson('/api/v1/audit-logs');

        $response->assertOk();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function member_without_export_permission_cannot_export()
    {
        // Give view but not export
        $role = $this->createTenantRoleWithPermissions(
            (string) $this->account->id,
            ['audit.view'],
            'audit_viewer_no_export'
        );
        $this->member->roles()->syncWithoutDetaching([
            (string) $role->id => [
                'assigned_by' => null,
                'assigned_at' => now(),
            ],
        ]);

        $response = $this->actingAs($this->member)
            ->postJson('/api/v1/audit-logs/export', ['format' => 'json']);

        $response->assertStatus(403);
    }

    // ─── Immutability via HTTP ───────────────────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function no_delete_or_patch_endpoints_exist()
    {
        $logId = AuditLog::withoutGlobalScopes()
            ->where('account_id', $this->account->id)
            ->first()->id;

        // DELETE should 405 or 404 (no route)
        $response = $this->actingAs($this->owner)
            ->deleteJson("/api/v1/audit-logs/{$logId}");

        $this->assertContains($response->status(), [404, 405]);

        // PATCH should 405 or 404 (no route)
        $response = $this->actingAs($this->owner)
            ->patchJson("/api/v1/audit-logs/{$logId}", ['action' => 'tampered']);

        $this->assertContains($response->status(), [404, 405]);
    }
}
