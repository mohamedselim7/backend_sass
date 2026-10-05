<?php

namespace Tests\Unit;

use App\Exceptions\InsufficientCreditsException;
use App\Models\User;
use App\Services\CreditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreditServiceTest extends TestCase
{
    use RefreshDatabase;

    private CreditService $credits;

    protected function setUp(): void
    {
        parent::setUp();
        $this->credits = app(CreditService::class);
    }

    public function test_charging_reduces_the_balance(): void
    {
        $user = User::factory()->create();
        $this->credits->grant($user, 30);

        $this->credits->charge($user, 'content_post', 5);

        $this->assertSame(25, $this->credits->balance($user));
    }

    public function test_charging_beyond_the_balance_throws(): void
    {
        $user = User::factory()->create();
        $this->credits->grant($user, 3);

        $this->expectException(InsufficientCreditsException::class);
        $this->credits->charge($user, 'content_post', 10);
    }

    public function test_the_same_idempotency_key_charges_once(): void
    {
        $user = User::factory()->create();
        $this->credits->grant($user, 50);

        $this->credits->charge($user, 'design_generate', 5, null, 'op-1');
        $this->credits->charge($user, 'design_generate', 5, null, 'op-1');

        $this->assertSame(45, $this->credits->balance($user));
    }

    public function test_a_refund_returns_the_charged_amount(): void
    {
        $user = User::factory()->create();
        $this->credits->grant($user, 20);
        $charge = $this->credits->charge($user, 'design_generate', 5);

        $this->credits->refund($user, $charge);

        $this->assertSame(20, $this->credits->balance($user));
    }
}
