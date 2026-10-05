<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrandTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_only_sees_their_own_brands(): void
    {
        $user = $this->actingAsUser();
        Brand::factory()->create(['created_by' => $user->getKey(), 'name' => 'Mine']);
        Brand::factory()->create(['created_by' => User::factory()->create()->getKey(), 'name' => 'Theirs']);

        $this->getJson('/api/v1/brands')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Mine');
    }

    public function test_a_user_cannot_read_another_users_brand(): void
    {
        $this->actingAsUser();
        $other = Brand::factory()->create();

        $this->getJson("/api/v1/brands/{$other->getKey()}")->assertStatus(403);
    }

    public function test_an_admin_can_read_any_brand(): void
    {
        $this->actingAsUser([], 'admin');
        $other = Brand::factory()->create();

        $this->getJson("/api/v1/brands/{$other->getKey()}")->assertOk();
    }

    public function test_brand_creation_validates_input(): void
    {
        $this->actingAsUser();

        $this->postJson('/api/v1/brands', ['name' => ''])
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }
}
