<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountRegistrationApiTest extends TestCase
{
    use RefreshDatabase;

    // ─── AC: نجاح — تسجيل حساب جديد عبر API ─────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_registers_a_new_account_via_api(): void
    {
        $response = $this->postJson('/api/v1/register', [
            'account_name'          => 'شركة الشحن الدولي',
            'account_type'          => 'organization',
            'name'                  => 'محمد أحمد',
            'email'                 => 'mohammed@shipping.com',
            'password'              => 'Str0ng!Pass',
            'password_confirmation' => 'Str0ng!Pass',
            'timezone'              => 'Asia/Riyadh',
        ]);

        $response->assertStatus(201)
                 ->assertJsonStructure([
                     'success',
                     'message',
                     'data' => [
                         'account' => ['id', 'name', 'type', 'status', 'slug', 'settings'],
                         'user'    => ['id', 'name', 'email', 'is_owner'],
                         'token',
                     ],
                 ])
                 ->assertJsonPath('data.account.type', 'organization')
                 ->assertJsonPath('data.user.is_owner', true);
    }

    // ─── AC: فشل شائع — بريد إلكتروني مكرر ─────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_rejects_duplicate_email(): void
    {
        // First registration
        $this->postJson('/api/v1/register', [
            'account_name'          => 'Company A',
            'name'                  => 'User A',
            'email'                 => 'duplicate@test.com',
            'password'              => 'Str0ng!Pass',
            'password_confirmation' => 'Str0ng!Pass',
        ])->assertStatus(201);

        // Attempt duplicate
        $response = $this->postJson('/api/v1/register', [
            'account_name'          => 'Company B',
            'name'                  => 'User B',
            'email'                 => 'duplicate@test.com',
            'password'              => 'Str0ng!Pass',
            'password_confirmation' => 'Str0ng!Pass',
        ]);

        $response->assertStatus(422)
                 ->assertJsonPath('error_code', 'ERR_DUPLICATE_EMAIL');
    }

    // ─── AC: حالة حدية — اسم حساب طويل جداً ────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_rejects_account_name_exceeding_max_length(): void
    {
        $response = $this->postJson('/api/v1/register', [
            'account_name'          => str_repeat('ا', 200), // 200 chars > max 150
            'name'                  => 'User',
            'email'                 => 'long@test.com',
            'password'              => 'Str0ng!Pass',
            'password_confirmation' => 'Str0ng!Pass',
        ]);

        $response->assertStatus(422)
                 ->assertJsonPath('error_code', 'ERR_INVALID_INPUT');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/register', []);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['account_name', 'name', 'email', 'password']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_validates_password_strength(): void
    {
        $response = $this->postJson('/api/v1/register', [
            'account_name'          => 'Test',
            'name'                  => 'User',
            'email'                 => 'weak@test.com',
            'password'              => '123',
            'password_confirmation' => '123',
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['password']);
    }
}
