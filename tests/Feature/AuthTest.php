<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_returns_a_token_and_grants_signup_credits(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Sara',
            'email' => 'sara@example.com',
            'password' => 'secret12345',
            'password_confirmation' => 'secret12345',
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['access_token', 'user' => ['id', 'email', 'roles']]);

        $user = User::where('email', 'sara@example.com')->firstOrFail();

        $this->assertTrue($user->hasRole('user'));
        $this->assertSame((int) config('payments.signup_credits'), (int) $user->wallet->balance);
    }

    public function test_login_rejects_wrong_password(): void
    {
        User::factory()->create(['email' => 'a@example.com', 'password' => 'secret12345']);

        $this->postJson('/api/v1/auth/login', ['email' => 'a@example.com', 'password' => 'wrong'])
            ->assertStatus(401)
            ->assertJsonPath('error', 'invalid_credentials');
    }

    public function test_suspended_user_cannot_use_the_api(): void
    {
        $this->actingAsUser(['status' => 'suspended']);

        $this->getJson('/api/v1/brands')
            ->assertStatus(403)
            ->assertJsonPath('error', 'account_suspended');
    }

    public function test_unverified_user_cannot_write(): void
    {
        $this->actingAsUser(['email_verified_at' => null]);

        $this->postJson('/api/v1/brands', ['name' => 'Test'])
            ->assertStatus(403)
            ->assertJsonPath('error', 'email_not_verified');
    }

    public function test_protected_route_requires_a_token(): void
    {
        $this->getJson('/api/v1/brands')->assertStatus(401);
    }
}
