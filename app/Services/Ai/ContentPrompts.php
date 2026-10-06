<?php

namespace App\Services\Ai;

use App\Services\Ai\Prompts\PromptContract;

/**
 * Prompt builders for social content plans and their "proposed design" (design_idea).
 * Kept separate from ContentGenerator so the wording can evolve without touching the flow.
 */
final class ContentPrompts
{
    /** Bump whenever system()/designSystem() wording changes meaningfully; stored on generations/designs for audit. */
    public const VERSION = 'content.v2026-10-06.2';

    private const LANGUAGES = [
        'arabic' => 'Arabic', 'ar' => 'Arabic', 'english' => 'English', 'en' => 'English',
    ];

    private const DIALECTS = [
        'egyptian' => 'Egyptian Arabic', 'gulf' => 'Gulf Arabic', 'saudi' => 'Saudi Arabic',
        'levantine' => 'Levantine Arabic', 'msa' => 'Modern Standard Arabic', 'fusha' => 'Modern Standard Arabic',
        'neutral_arabic' => 'clear, neutral Arabic close to Modern Standard Arabic',
    ];

    public static function system(string $mandatoryDesignPrompt, bool $isCarousel): string
    {
        return PromptContract::make()
            ->role('You are a senior social-media strategist and conversion copywriter for personal brands and businesses in the Arab market. You write publish-ready posts that stop the scroll, deliver one clear idea, and move the reader to act.')
            ->task('Write the content plan(s) described in the user message: exactly the requested number of plans and posts per plan, for the requested platforms, language and tone. Every post gets a non-empty design_idea.')
            ->context('Everything you know about the brand, audience and goal is in the user message (BRIEF and the labelled lines after it). Treat it as the only source of facts.')
            ->userConstraints(...self::mandatoryLines($mandatoryDesignPrompt))
            ->outputContract(<<<'JSON'
Return ONLY one JSON object, no markdown, no commentary:
{"plans":[{"name":string,"strategy":string,"goal":string,"pillars":string[],"funnel":string[],"formats":string[],
"posts":[{"headline":string,"content":string,"cta":string,"design_idea":string,"platform":string,"content_type":string,"funnel_stage":string,"hashtags":string[]}]}]}
JSON)
            ->qualityCriteria(
                'Hook: the first line earns attention in under 2 seconds with a sharp insight, concrete number, tension or bold claim tied to the audience\'s real problem.',
                'One idea per post. Structure: hook -> the problem or insight in concrete terms -> value (steps, example, proof or reframe) -> one clear CTA.',
                'Specific beats generic: real situations, outcomes and the audience\'s own words, grounded in the brief.',
                'Readability: short paragraphs (1-3 lines), line breaks between ideas, simple words, 0-2 emoji only if natural for the tone.',
                'CTA: one concrete, low-friction action matched to the funnel stage (comment a word, save, DM, book, visit).',
                'Variety across the plan: rotate angles (story, myth vs fact, mistake, how-to, case, opinion, checklist) and funnel stages.',
                'headline is a short internal title, not clickbait. Hashtags: 3-6 relevant ones.',
                ...self::designCriteria($isCarousel),
            )
            ->negativeConstraints(
                'No greetings or generic openers ("في عالم اليوم", "هل تعلم").',
                'Never invent statistics, clients, testimonials or results.',
                'No two posts share the same hook pattern or headline; never repeat a headline listed under DO NOT REPEAT.',
                'Never use "تواصل معنا" alone as the CTA.',
                'No filler, no repeated sentences, no hashtag spam.',
                'Never write a vague design_idea such as "تصميم جذاب وعصري".',
            )
            ->build();
    }

    public static function designSystem(string $mandatoryDesignPrompt, bool $isCarousel): string
    {
        return PromptContract::make()
            ->role('You are a senior art director for social-media content in the Arab market.')
            ->task('For each post you receive, write its "design_idea" ("التصميم المقترح"): executable art direction for a designer.')
            ->context('Each post arrives with a "ref" id and its copy. The visual must make that post\'s hook understandable at a glance.')
            ->userConstraints(...self::mandatoryLines($mandatoryDesignPrompt))
            ->outputContract('Return ONLY JSON: {"posts":[{"ref":string,"design_idea":string}]} using exactly the "ref" values you received, one entry per post.')
            ->qualityCriteria(...self::designCriteria($isCarousel))
            ->negativeConstraints(
                'Never write vague direction ("تصميم جذاب وعصري").',
                'Never invent claims, prices or numbers not present in the post.',
                'Never add or drop posts, and never change a ref.',
            )
            ->build();
    }

    /** @return list<string> */
    private static function designCriteria(bool $isCarousel): array
    {
        $lines = [
            'design_idea is written in Arabic as art direction for a designer, never as text to be printed as-is.',
            'design_idea uses short labelled lines: الفكرة البصرية، التكوين، النص على التصميم (3-8 words from the post, optional sub-line)، الألوان والإضاءة، الخطوط، العناصر، أين يوضع اللوجو.',
            'design_idea is specific and executable (e.g. "صورة مقربة ليد تمسك فاتورة مشطوبة بالأحمر، خلفية رمادية فاتحة"). Default canvas is a 1:1 square; keep the focal point central so it survives a centre crop.',
        ];
        if ($isCarousel) {
            $lines[] = 'This is a CAROUSEL: write a numbered page-by-page sequence (صفحة 1: الغلاف ... الصفحة الأخيرة: الدعوة لاتخاذ إجراء), one idea per page, consistent style across pages.';
        }

        return $lines;
    }

    /**
     * The Planning & Understanding prompt is never dropped: when present it is
     * rendered as a BINDING user constraint above every default.
     *
     * @return list<string>
     */
    public static function mandatoryLines(string $mandatoryDesignPrompt): array
    {
        $mandatoryDesignPrompt = trim($mandatoryDesignPrompt);
        if ($mandatoryDesignPrompt === '') {
            return [];
        }

        return [
            "MANDATORY DESIGN PROMPT from the user's Planning & Understanding section, binding for EVERY design_idea:\n<<<\n{$mandatoryDesignPrompt}\n>>>",
            'Apply every instruction in it literally (style, colors, fonts, number of pages, image type, wording). If it conflicts with any default, it wins.',
            'Start each design_idea with a line "الالتزام بالبرومبت الإلزامي:" stating how it was applied.',
        ];
    }

    public static function request(array $input, string $brief, array $platforms, int $planCount, int $postsPerPlan): string
    {
        $lines = [];

        if (($input['mode'] ?? 'plan') === 'single') {
            $lines[] = 'MODE: write exactly ONE strong post from the request below only (no extra brand context).';
        }

        $lines[] = "BRIEF:\n".$brief;
        $lines[] = 'PLATFORMS: '.implode(', ', $platforms);
        $lines[] = 'NUMBER OF PLANS: '.$planCount;
        $lines[] = 'POSTS PER PLAN: '.$postsPerPlan.' (return exactly this many posts)';

        if (! empty($input['target_audience'])) {
            $lines[] = 'TARGET AUDIENCE: '.$input['target_audience'];
        }
        if (! empty($input['target_market'])) {
            $lines[] = 'TARGET MARKET: '.$input['target_market'];
        }

        $languageId = strtolower((string) ($input['language_id'] ?? ''));
        $language = self::LANGUAGES[$languageId] ?? null;
        $dialectId = strtolower((string) ($input['dialect_id'] ?? ''));
        $dialect = self::DIALECTS[$dialectId] ?? ($dialectId !== '' ? ucfirst($dialectId).' Arabic dialect' : null);
        if ($languageId === 'arabic_english' || $languageId === 'arabic_business_english') {
            $lines[] = 'LANGUAGE: Arabic'.($dialect ? ' ('.$dialect.')' : '').', keeping common business/marketing terms in English where natural.';
        } elseif ($language === 'English') {
            $lines[] = 'LANGUAGE: write headline, content, cta and hashtags in English. design_idea stays in Arabic.';
        } else {
            $lines[] = 'LANGUAGE: Arabic'.($dialect ? ' — write the post copy in '.$dialect.'.' : ' — clear, natural Arabic.');
        }
        if (! empty($input['tone_id'])) {
            $lines[] = 'TONE OF VOICE: '.$input['tone_id'];
        }
        if (! empty($input['content_format'])) {
            $lines[] = 'CONTENT FORMAT FOR EVERY POST: '.$input['content_format'];
        }

        $avoid = array_filter(array_map('strval', (array) ($input['avoid_headlines'] ?? [])));
        if ($avoid !== []) {
            $lines[] = "DO NOT REPEAT THESE PREVIOUS HEADLINES OR THEIR IDEAS:\n- ".implode("\n- ", array_slice($avoid, 0, 80));
        }

        return implode("\n\n", $lines);
    }
}
