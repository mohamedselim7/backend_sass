<?php

namespace Tests\Unit\Queue;

use App\Models\Plan;
use App\Services\Queue\PlanQueueResolver;
use Tests\TestCase;

class PlanQueueResolverTest extends TestCase
{
    public function test_no_plan_uses_default_lane(): void
    {
        $this->assertSame('ai-default', app(PlanQueueResolver::class)->resolveForPlan(null));
    }

    public function test_explicit_queue_on_plan_wins(): void
    {
        $plan = Plan::factory()->make(['ai_queue' => 'ai-high', 'queue_priority' => 3]);
        $this->assertSame('ai-high', app(PlanQueueResolver::class)->resolveForPlan($plan));
    }

    public function test_priority_maps_to_lane_for_any_admin_created_plan(): void
    {
        $r = app(PlanQueueResolver::class);
        $this->assertSame('ai-high', $r->resolveForPlan(Plan::factory()->make(['code' => 'brand-new-tier', 'queue_priority' => 1])));
        $this->assertSame('ai-low', $r->resolveForPlan(Plan::factory()->make(['queue_priority' => 3])));
    }

    public function test_price_and_name_do_not_affect_lane(): void
    {
        $plan = Plan::factory()->make(['name' => 'Pro', 'price' => 999999, 'monthly_credits' => 100000]);
        $this->assertSame('ai-default', app(PlanQueueResolver::class)->resolveForPlan($plan));
    }
}
