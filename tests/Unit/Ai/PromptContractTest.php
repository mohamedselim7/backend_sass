<?php

namespace Tests\Unit\Ai;

use App\Services\Ai\ContentPrompts;
use Tests\TestCase;

class PromptContractTest extends TestCase
{
    public function test_content_system_prompt_has_all_sections_in_priority_order(): void
    {
        $prompt = ContentPrompts::system('', false);
        $order = ['SYSTEM/ROLE:', 'TASK:', 'CONTEXT:', 'OUTPUT CONTRACT', 'QUALITY CRITERIA', 'NEGATIVE CONSTRAINTS'];
        $last = -1;
        foreach ($order as $label) {
            $pos = strpos($prompt, $label);
            $this->assertNotFalse($pos, "missing {$label}");
            $this->assertGreaterThan($last, $pos);
            $last = $pos;
        }
        $this->assertStringNotContainsString('USER CONSTRAINTS', $prompt);
    }

    public function test_mandatory_planning_prompt_is_never_dropped_and_ranks_above_defaults(): void
    {
        foreach ([ContentPrompts::system('استخدم اللون الأخضر فقط', true), ContentPrompts::designSystem('استخدم اللون الأخضر فقط', false)] as $prompt) {
            $this->assertStringContainsString('استخدم اللون الأخضر فقط', $prompt);
            $this->assertLessThan(strpos($prompt, 'QUALITY CRITERIA'), strpos($prompt, 'USER CONSTRAINTS'));
        }
    }

    public function test_carousel_adds_page_sequence(): void
    {
        $this->assertStringContainsString('CAROUSEL', ContentPrompts::system('', true));
    }

    public function test_design_format_defaults_to_square(): void
    {
        $this->assertSame('1:1', config('design.default_format'));
        $this->assertSame('1024x1024', config('design.formats.1:1.size'));
    }
}
