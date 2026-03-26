<?php

namespace Tests\Feature\Web;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuthSurfaceSecurityTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function login_pages_do_not_expose_seeded_credentials_or_shared_passwords_by_default(): void
    {
        foreach ([
            '/b2c/login',
            '/b2b/login',
            '/admin/login',
        ] as $path) {
            $response = $this->get($path);

            $response->assertOk();
            $response->assertDontSeeText('e2e.a.individual@example.test');
            $response->assertDontSeeText('e2e.c.organization_owner@example.test');
            $response->assertDontSeeText('e2e.internal.super_admin@example.test');
            $response->assertDontSeeText('Password123!');
        }
    }
}
