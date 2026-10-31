<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_users_can_register(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'New-Password-2026!',
            'password_confirmation' => 'New-Password-2026!',
            'phone' => '0612345678',
            'birthday' => '1995-01-01',
        ]);

        $this->assertAuthenticated();
        $response->assertNoContent();
    }
}
