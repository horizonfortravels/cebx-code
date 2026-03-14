<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class UuidPhase1PreflightGuardMigrationTest extends TestCase
{
    use RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_fails_with_clear_message_when_polymorphic_user_reference_is_orphaned(): void
    {
        if (!Schema::hasTable('personal_access_tokens')) {
            Schema::create('personal_access_tokens', function (Blueprint $table): void {
                $table->id();
                $table->morphs('tokenable');
                $table->text('name');
                $table->string('token', 64)->unique();
                $table->text('abilities')->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamps();
            });
        }

        DB::table('personal_access_tokens')->insert([
            'tokenable_type' => 'App\\Models\\User',
            'tokenable_id' => 999999,
            'name' => 'orphan-token',
            'token' => hash('sha256', Str::random(40)),
            'abilities' => json_encode(['*']),
            'last_used_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration = require database_path('migrations/2026_03_05_000100_uuid_phase1_preflight_guard.php');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('personal_access_tokens.tokenable_id');
        $this->expectExceptionMessage('999999');

        $migration->up();
    }
}
