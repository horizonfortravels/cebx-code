<?php

namespace Tests\Unit;

use App\Models\ImmutableAuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use LogicException;
use Tests\TestCase;

class ImmutableAuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_immutable_audit_log_schema_no_longer_exposes_updated_at(): void
    {
        $this->assertFalse(Schema::hasColumn('immutable_audit_log', 'updated_at'));
    }

    public function test_immutable_audit_log_rejects_updates(): void
    {
        $log = $this->createAuditLog();

        $this->expectException(LogicException::class);

        $log->update(['actor_name' => 'Tampered']);
    }

    public function test_immutable_audit_log_rejects_deletes(): void
    {
        $log = $this->createAuditLog();

        $this->expectException(LogicException::class);

        $log->delete();
    }

    private function createAuditLog(): ImmutableAuditLog
    {
        return ImmutableAuditLog::query()->create([
            'account_id' => (string) Str::uuid(),
            'event_type' => 'shipment.purchased',
            'entity_type' => 'shipment',
            'entity_id' => (string) Str::uuid(),
            'payload' => ['status' => 'purchased'],
            'hash' => str_repeat('a', 64),
            'previous_hash' => null,
            'occurred_at' => now(),
        ]);
    }
}
