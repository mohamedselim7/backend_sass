<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_regular_user_is_blocked_from_admin_routes(): void
    {
        $this->actingAsUser();

        $this->getJson('/api/v1/admin/stats')->assertStatus(403);
    }

    public function test_admin_can_read_stats_and_adjust_credits(): void
    {
        $this->actingAsUser([], 'admin');
        $target = $this->createTargetUser();

        $this->getJson('/api/v1/admin/stats')->assertOk()->assertJsonStructure(['users', 'revenue']);

        $this->postJson("/api/v1/admin/users/{$target->getKey()}/credits", ['amount' => 50])
            ->assertOk()
            ->assertJsonPath('balance', 50);
    }

    private function createTargetUser(): \App\Models\User
    {
        $user = \App\Models\User::factory()->create();
        $user->assignRole('user');

        return $user;
    }
}
