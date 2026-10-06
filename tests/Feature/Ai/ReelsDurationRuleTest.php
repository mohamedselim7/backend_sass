<?php

namespace Tests\Feature\Ai;

use App\Http\Requests\Ai\ReelsRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class ReelsDurationRuleTest extends TestCase
{
    public function test_duration_accepts_the_text_the_site_sends(): void
    {
        $rules = array_intersect_key((new ReelsRequest())->rules(), ['duration' => 1, 'count' => 1]);

        $this->assertTrue(Validator::make(['count' => 5, 'duration' => '20-30 ثانية'], $rules)->passes());
        $this->assertTrue(Validator::make(['count' => 5, 'duration' => '60 ثانية'], $rules)->passes());
        $this->assertTrue(Validator::make(['count' => 5], $rules)->passes());
    }
}
