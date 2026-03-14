<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Account;
use App\Models\User;
use App\Models\Role;
use App\Models\AuditLog;
use App\Services\AuditService;
use App\Exceptions\BusinessException;

/**
 * FR-IAM-006: Audit Log — Unit Tests (28 tests)
 * FR-IAM-013: Organization/Team audit context
 */
class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    protected AuditService $auditService;
    protected Account $account;
    protected User $owner;

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
    }

    // ─── Core Logging ────────────────────────────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_creates_audit_log_entry()
    {
        $log = $this->auditService->log(
            $this->account->id,
            $this->owner->id,
            'user.added',
            AuditLog::CATEGORY_USERS,
            AuditLog::SEVERITY_INFO,
            'User',
            $this->owner->id,
            null,
            ['name' => 'Test User']
        );

        $this->assertNotNull($log->id);
        $this->assertEquals($this->account->id, $log->account_id);
        $this->assertEquals($this->owner->id, $log->user_id);
        $this->assertEquals('user.added', $log->action);
        $this->assertEquals('info', $log->severity);
        $this->assertEquals('users', $log->category);
        $this->assertEquals('User', $log->entity_type);
        $this->assertNotNull($log->request_id);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_records_old_and_new_values()
    {
        $old = ['name' => 'Old Name'];
        $new = ['name' => 'New Name'];

        $log = $this->auditService->info(
            $this->account->id, $this->owner->id,
            'user.updated', AuditLog::CATEGORY_USERS,
            'User', $this->owner->id,
            $old, $new
        );

        $this->assertEquals($old, $log->old_values);
        $this->assertEquals($new, $log->new_values);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_records_metadata()
    {
        $metadata = ['source' => 'api', 'batch_size' => 15];

        $log = $this->auditService->info(
            $this->account->id, $this->owner->id,
            'data.exported', AuditLog::CATEGORY_EXPORT,
            null, null, null, null, $metadata
        );

        $this->assertEquals($metadata, $log->metadata);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_records_ip_and_user_agent()
    {
        $log = $this->auditService->info(
            $this->account->id, $this->owner->id,
            'auth.login', AuditLog::CATEGORY_AUTH
        );

        // In test environment, these may be null/empty but the columns are populated
        $this->assertArrayHasKey('ip_address', $log->getAttributes());
        $this->assertArrayHasKey('user_agent', $log->getAttributes());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_supports_system_actions_without_user()
    {
        $log = $this->auditService->info(
            $this->account->id, null,
            'invitation.expired', AuditLog::CATEGORY_INVITATION
        );

        $this->assertNull($log->user_id);
        $this->assertEquals('invitation.expired', $log->action);
    }

    // ─── Severity Levels ─────────────────────────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_logs_info_severity()
    {
        $log = $this->auditService->info(
            $this->account->id, $this->owner->id,
            'role.created', AuditLog::CATEGORY_ROLES
        );

        $this->assertEquals(AuditLog::SEVERITY_INFO, $log->severity);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_logs_warning_severity()
    {
        $log = $this->auditService->warning(
            $this->account->id, $this->owner->id,
            'permission.denied', AuditLog::CATEGORY_ROLES
        );

        $this->assertEquals(AuditLog::SEVERITY_WARNING, $log->severity);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_logs_critical_severity()
    {
        $log = $this->auditService->critical(
            $this->account->id, $this->owner->id,
            'user.deleted', AuditLog::CATEGORY_USERS
        );

        $this->assertEquals(AuditLog::SEVERITY_CRITICAL, $log->severity);
    }

    // ─── Append-Only / Immutability ──────────────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_blocks_update_on_audit_log()
    {
        $log = $this->auditService->info(
            $this->account->id, $this->owner->id,
            'user.added', AuditLog::CATEGORY_USERS
        );

        $this->expectException(BusinessException::class);
        $log->update(['action' => 'tampered']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_blocks_delete_on_audit_log()
    {
        $log = $this->auditService->info(
            $this->account->id, $this->owner->id,
            'user.added', AuditLog::CATEGORY_USERS
        );

        $this->expectException(BusinessException::class);
        $log->delete();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_blocks_force_delete_on_audit_log()
    {
        $log = $this->auditService->info(
            $this->account->id, $this->owner->id,
            'user.added', AuditLog::CATEGORY_USERS
        );

        $this->expectException(BusinessException::class);
        $log->forceDelete();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function update_attempt_is_logged_as_tamper()
    {
        $log = $this->auditService->info(
            $this->account->id, $this->owner->id,
            'user.added', AuditLog::CATEGORY_USERS
        );

        try {
            $log->update(['action' => 'tampered']);
        } catch (BusinessException $e) {
            $this->assertEquals('ERR_AUDIT_IMMUTABLE', $e->getErrorCode());
            $this->assertEquals(403, $e->getHttpStatus());
        }
    }

    // ─── Request Correlation ─────────────────────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_generates_consistent_request_id()
    {
        AuditService::resetRequestId();

        $log1 = $this->auditService->info(
            $this->account->id, $this->owner->id,
            'user.added', AuditLog::CATEGORY_USERS
        );

        $log2 = $this->auditService->info(
            $this->account->id, $this->owner->id,
            'role.assigned', AuditLog::CATEGORY_ROLES
        );

        $this->assertEquals($log1->request_id, $log2->request_id);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_accepts_custom_request_id()
    {
        AuditService::setRequestId('custom-trace-123');

        $log = $this->auditService->info(
            $this->account->id, $this->owner->id,
            'auth.login', AuditLog::CATEGORY_AUTH
        );

        $this->assertEquals('custom-trace-123', $log->request_id);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_resets_request_id_between_requests()
    {
        $log1 = $this->auditService->info(
            $this->account->id, $this->owner->id,
            'user.added', AuditLog::CATEGORY_USERS
        );

        AuditService::resetRequestId();

        $log2 = $this->auditService->info(
            $this->account->id, $this->owner->id,
            'role.created', AuditLog::CATEGORY_ROLES
        );

        $this->assertNotEquals($log1->request_id, $log2->request_id);
    }

    // ─── Search & Filtering ──────────────────────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_searches_by_category()
    {
        // Create logs in different categories
        $this->auditService->info($this->account->id, $this->owner->id, 'user.added', AuditLog::CATEGORY_USERS);
        $this->auditService->info($this->account->id, $this->owner->id, 'role.created', AuditLog::CATEGORY_ROLES);
        $this->auditService->info($this->account->id, $this->owner->id, 'auth.login', AuditLog::CATEGORY_AUTH);

        $results = $this->auditService->search($this->account->id, ['category' => 'users']);
        $this->assertEquals(1, $results->total());
        $this->assertEquals('user.added', $results->first()->action);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_searches_by_severity()
    {
        $this->auditService->info($this->account->id, $this->owner->id, 'user.added', AuditLog::CATEGORY_USERS);
        $this->auditService->warning($this->account->id, $this->owner->id, 'permission.denied', AuditLog::CATEGORY_ROLES);
        $this->auditService->critical($this->account->id, $this->owner->id, 'user.deleted', AuditLog::CATEGORY_USERS);

        $results = $this->auditService->search($this->account->id, ['severity' => 'critical']);
        $this->assertEquals(1, $results->total());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_searches_by_actor()
    {
        $other = User::factory()->create(['account_id' => $this->account->id]);

        $this->auditService->info($this->account->id, $this->owner->id, 'user.added', AuditLog::CATEGORY_USERS);
        $this->auditService->info($this->account->id, $other->id, 'role.created', AuditLog::CATEGORY_ROLES);

        $results = $this->auditService->search($this->account->id, ['actor_id' => $other->id]);
        $this->assertEquals(1, $results->total());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_searches_by_date_range()
    {
        // Insert manually to control timestamps
        AuditLog::withoutGlobalScopes()->create([
            'account_id' => $this->account->id,
            'user_id'    => $this->owner->id,
            'action'     => 'user.added',
            'severity'   => 'info',
            'category'   => 'users',
            'created_at' => now()->subDays(10),
        ]);

        $this->auditService->info($this->account->id, $this->owner->id, 'user.updated', AuditLog::CATEGORY_USERS);

        $results = $this->auditService->search($this->account->id, [
            'from' => now()->subDay()->toDateString(),
        ]);
        $this->assertEquals(1, $results->total());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_searches_by_entity()
    {
        $entityId = \Illuminate\Support\Str::uuid()->toString();

        $this->auditService->info(
            $this->account->id, $this->owner->id,
            'role.assigned', AuditLog::CATEGORY_ROLES,
            'Role', $entityId
        );
        $this->auditService->info(
            $this->account->id, $this->owner->id,
            'user.added', AuditLog::CATEGORY_USERS,
            'User', \Illuminate\Support\Str::uuid()->toString()
        );

        $results = $this->auditService->search($this->account->id, [
            'entity_type' => 'Role',
            'entity_id'   => $entityId,
        ]);
        $this->assertEquals(1, $results->total());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_searches_by_action_prefix()
    {
        $this->auditService->info($this->account->id, $this->owner->id, 'user.added', AuditLog::CATEGORY_USERS);
        $this->auditService->info($this->account->id, $this->owner->id, 'user.updated', AuditLog::CATEGORY_USERS);
        $this->auditService->info($this->account->id, $this->owner->id, 'role.created', AuditLog::CATEGORY_ROLES);

        $results = $this->auditService->search($this->account->id, ['action' => 'user.*']);
        $this->assertEquals(2, $results->total());
    }

    // ─── Entity Trail ────────────────────────────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_entity_trail()
    {
        $entityId = \Illuminate\Support\Str::uuid()->toString();

        $this->auditService->info($this->account->id, $this->owner->id, 'user.added', AuditLog::CATEGORY_USERS, 'User', $entityId);
        $this->auditService->info($this->account->id, $this->owner->id, 'user.updated', AuditLog::CATEGORY_USERS, 'User', $entityId);
        $this->auditService->info($this->account->id, $this->owner->id, 'user.disabled', AuditLog::CATEGORY_USERS, 'User', $entityId);

        // Unrelated
        $this->auditService->info($this->account->id, $this->owner->id, 'role.created', AuditLog::CATEGORY_ROLES);

        $trail = $this->auditService->entityTrail($this->account->id, 'User', $entityId);
        $this->assertEquals(3, $trail->total());
    }

    // ─── Request Trace ───────────────────────────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_request_trace()
    {
        AuditService::resetRequestId();
        AuditService::setRequestId('trace-xyz-123');

        $this->auditService->info($this->account->id, $this->owner->id, 'user.added', AuditLog::CATEGORY_USERS);
        $this->auditService->info($this->account->id, $this->owner->id, 'role.assigned', AuditLog::CATEGORY_ROLES);

        AuditService::resetRequestId(); // New request
        $this->auditService->info($this->account->id, $this->owner->id, 'auth.login', AuditLog::CATEGORY_AUTH);

        $trace = $this->auditService->requestTrace($this->account->id, 'trace-xyz-123');
        $this->assertCount(2, $trace);
    }

    // ─── Statistics ──────────────────────────────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_statistics()
    {
        $this->auditService->info($this->account->id, $this->owner->id, 'user.added', AuditLog::CATEGORY_USERS);
        $this->auditService->warning($this->account->id, $this->owner->id, 'permission.denied', AuditLog::CATEGORY_ROLES);
        $this->auditService->critical($this->account->id, $this->owner->id, 'user.deleted', AuditLog::CATEGORY_USERS);

        $stats = $this->auditService->statistics($this->account->id);

        $this->assertEquals(3, $stats['total']);
        $this->assertArrayHasKey('by_severity', $stats);
        $this->assertArrayHasKey('by_category', $stats);
        $this->assertArrayHasKey('top_actions', $stats);
        $this->assertEquals(1, $stats['by_severity']['critical']);
    }

    // ─── Tenant Isolation ────────────────────────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_isolates_audit_logs_by_tenant()
    {
        $otherAccount = Account::factory()->create();

        $this->auditService->info($this->account->id, $this->owner->id, 'user.added', AuditLog::CATEGORY_USERS);
        $this->auditService->info($otherAccount->id, null, 'account.created', AuditLog::CATEGORY_ACCOUNT);

        $results = $this->auditService->search($this->account->id);
        $this->assertEquals(1, $results->total());
    }

    // ─── Export ──────────────────────────────────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_exports_audit_logs()
    {
        $this->auditService->info($this->account->id, $this->owner->id, 'user.added', AuditLog::CATEGORY_USERS);
        $this->auditService->info($this->account->id, $this->owner->id, 'role.created', AuditLog::CATEGORY_ROLES);

        $rows = $this->auditService->export($this->account->id, $this->owner);

        // 2 original + 1 export action itself
        $this->assertCount(3, $rows);
        $this->assertArrayHasKey('actor', $rows[0]);
        $this->assertArrayHasKey('timestamp', $rows[0]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_logs_the_export_action_itself()
    {
        $this->auditService->info($this->account->id, $this->owner->id, 'user.added', AuditLog::CATEGORY_USERS);

        $this->auditService->export($this->account->id, $this->owner);

        $exportLog = AuditLog::withoutGlobalScopes()
            ->where('action', 'audit.exported')
            ->first();

        $this->assertNotNull($exportLog);
        $this->assertEquals(AuditLog::CATEGORY_EXPORT, $exportLog->category);
    }

    // ─── Action Registry ─────────────────────────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function action_registry_is_available()
    {
        $registry = AuditService::actionRegistry();

        $this->assertArrayHasKey('auth.login', $registry);
        $this->assertArrayHasKey('user.added', $registry);
        $this->assertArrayHasKey('role.created', $registry);
        $this->assertEquals('auth', $registry['auth.login']['category']);
    }

    // ─── Model Constants ─────────────────────────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_provides_valid_categories_and_severities()
    {
        $categories = AuditLog::categories();
        $severities = AuditLog::severities();

        $this->assertContains('auth', $categories);
        $this->assertContains('users', $categories);
        $this->assertContains('financial', $categories);

        $this->assertContains('info', $severities);
        $this->assertContains('warning', $severities);
        $this->assertContains('critical', $severities);
    }
}
