<?php

namespace App\Services\Ai;

/**
 * Prompt builders for social content plans and their "proposed design" (design_idea).
 * Kept separate from ContentGenerator so the wording can evolve without touching the flow.
 */
final class ContentPrompts
{
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
        $design = self::designRules($mandatoryDesignPrompt, $isCarousel);

        return <<<TXT
You are a senior social-media strategist and conversion copywriter for personal brands and businesses in the Arab market.
You write publish-ready posts that stop the scroll, deliver one clear idea, and move the reader to act.

WRITING STANDARD (every post):
- Hook: the first line must earn attention in under 2 seconds — a sharp insight, a concrete number, a tension, or a bold claim tied to the audience's real problem. No greetings, no generic openers ("في عالم اليوم", "هل تعلم").
- One idea per post. Structure: hook → the problem or insight in concrete terms → value (steps, example, proof, or reframe) → one clear CTA.
- Specific beats generic: use real situations, numbers, outcomes, and the audience's own words. Every post must be grounded in the brief; never invent statistics, clients, or results.
- Readability: short paragraphs (1–3 lines), line breaks between ideas, simple words, no filler, no repeated sentences, no emoji spam (0–2 max, only if natural for the tone).
- CTA: one concrete, low-friction action matched to the funnel stage (comment a word, save, DM, book, visit). Never "تواصل معنا" alone.
- Variety across the plan: rotate angles (story, myth vs. fact, mistake, how-to, case, opinion, checklist) and funnel stages; no two posts may share the same hook pattern or headline.
- The headline is an internal title for the post (short, clear), not clickbait.
- Hashtags: 3–6 relevant hashtags, no generic spam.

{$design}

OUTPUT: return ONLY a JSON object with exactly this shape:
{"plans":[{"name":string,"strategy":string,"goal":string,"pillars":array,"funnel":array,"formats":array,
"posts":[{"headline":string,"content":string,"cta":string,"design_idea":string,"platform":string,"content_type":string,"funnel_stage":string,"hashtags":array}]}]}
Every post MUST include a non-empty "design_idea".
TXT;
    }

    public static function designSystem(string $mandatoryDesignPrompt, bool $isCarousel): string
    {
        $design = self::designRules($mandatoryDesignPrompt, $isCarousel);

        return <<<TXT
You are a senior art director. For each social post you receive, write its "design_idea".
{$design}
Return ONLY JSON: {"posts":[{"ref":string,"design_idea":string}]} with the same "ref" values you received.
TXT;
    }

    private static function designRules(string $mandatoryDesignPrompt, bool $isCarousel): string
    {
        $rules = <<<TXT
DESIGN_IDEA STANDARD ("التصميم المقترح"), written in Arabic, as art direction for a designer — never text to be printed as-is:
- Derive it from THIS post's message: the visual must make the hook understandable at a glance.
- Include, as short labeled lines: الفكرة البصرية (the core visual metaphor/scene), التكوين (layout, focal point, hierarchy), النص على التصميم (a 3–8 word headline taken from the post, plus optional sub-line), الألوان والإضاءة, الخطوط, العناصر (icons/photos/illustration style), and أين يوضع اللوجو.
- Be specific and executable (e.g. "صورة مقربة ليد تمسك فاتورة مشطوبة بالأحمر، خلفية رمادية فاتحة"), never vague ("تصميم جذاب وعصري").
TXT;

        if ($isCarousel) {
            $rules .= "\n- This is a CAROUSEL: write a numbered page-by-page sequence (صفحة 1: الغلاف … الصفحة الأخيرة: الدعوة لاتخاذ إجراء), one idea per page, consistent style across pages.";
        }

        if ($mandatoryDesignPrompt !== '') {
            $rules .= "\n\nMANDATORY DESIGN PROMPT from the user's Planning & Understanding section — BINDING for EVERY design_idea, highest priority, no exceptions:\n<<<\n"
                .$mandatoryDesignPrompt
                ."\n>>>\n- Apply every instruction in it literally in each design_idea (style, colors, fonts, number of pages, image type, wording).\n- If it conflicts with the standard above, the mandatory prompt wins.\n- Start each design_idea with a line \"الالتزام بالبرومبت الإلزامي:\" that states how it was applied.";
        }

        return $rules;
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
