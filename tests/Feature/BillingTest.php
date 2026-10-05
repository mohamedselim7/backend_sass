<?php

namespace Tests\Feature;

use App\Models\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingTest extends TestCase
{
    use RefreshDatabase;

    public function test_plans_are_public(): void
    {
        Plan::factory()->create(['code' => 'pro', 'price' => 1299]);

        $this->getJson('/api/v1/plans')
            ->assertOk()
            ->assertJsonPath('data.0.code', 'pro')
            ->assertJsonPath('data.0.price', fn ($price) => (float) $price === 1299.0);
    }

    public function test_checkout_ignores_any_client_supplied_price(): void
    {
        $this->actingAsUser();
        Plan::factory()->create(['code' => 'pro', 'price' => 1299]);

        $this->postJson('/api/v1/billing/checkout', ['plan_code' => 'pro', 'price' => 1])
            ->assertCreated();

        $this->assertDatabaseHas('payments', ['amount' => 1299.00, 'status' => 'pending']);
    }

    public function test_unsigned_webhook_is_rejected(): void
    {
        $this->postJson('/api/v1/webhooks/payment', ['obj' => ['id' => 1, 'success' => true]])
            ->assertStatus(401);
    }
}
