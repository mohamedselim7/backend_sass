<?php

namespace App\Services\Ai;

use App\Models\User;
use App\Services\Ai\Contracts\ContentGeneratorInterface;
use Throwable;

/**
 * Real implementation of ContentGeneratorInterface backed by the configured
 * text provider. Used both synchronously (AiController) and from the queued
 * GenerateContentJob.
 *
 * Every post must carry a `design_idea` (art direction). When the user saved a
 * mandatory design prompt in "Planning & Understanding", it is passed as
 * `visual_identity` and bound into every design_idea.
 */
class ContentGenerator implements ContentGeneratorInterface
{
    /** Prompt version propagated to GenerateContentAction -> ContentService::storeGeneration. */
    public const VERSION = ContentPrompts::VERSION;

    public function __construct(private readonly ProviderResolver $resolver) {}

    public function generatePlan(array $input): array
    {
        /** @var User|null $user */
        $user = $input['_user'] ?? null;

        $options = array_filter([
            'provider' => $input['provider'] ?? null,
            'model' => $input['model'] ?? null,
        ]);

        $textProvider = $this->resolver->text($user, $options);
        $prompt = new JsonPrompt($textProvider);

        $planCount = max(1, (int) ($input['plan_count'] ?? 1));
        $postsPerPlan = max(1, (int) ($input['posts_per_plan'] ?? 5));
        $brief = (string) ($input['brief'] ?? $input['business_brief'] ?? $input['monthly_brief'] ?? '');
        $platforms = $input['platforms'] ?? [];
        $mandatory = trim((string) ($input['visual_identity'] ?? ''));
        $isCarousel = ($input['content_format'] ?? null) === 'carousel';

        $response = $prompt->ask(
            ContentPrompts::system($mandatory, $isCarousel),
            [['role' => 'user', 'content' => ContentPrompts::request($input, $brief, $platforms, $planCount, $postsPerPlan)]],
            $options,
        );

        $plans = $response['result']['plans'] ?? $response['result'] ?? [];
        $plans = array_values(is_array($plans) ? $plans : []);
        $plans = self::normalize($plans);

        return $this->fillMissingDesignIdeas($prompt, $plans, $mandatory, $isCarousel, $options);
    }

    /** Accept camelCase keys from the model and keep the stored shape stable. */
    public static function normalize(array $plans): array
    {
        foreach ($plans as $pi => $plan) {
            if (! is_array($plan)) {
                unset($plans[$pi]);

                continue;
            }
            $posts = is_array($plan['posts'] ?? null) ? array_values($plan['posts']) : [];
            foreach ($posts as $i => $post) {
                if (! is_array($post)) {
                    continue;
                }
                $idea = $post['design_idea'] ?? $post['designIdea'] ?? $post['proposed_design'] ?? null;
                if (is_array($idea)) {
                    $idea = implode("\n", array_map(fn ($line) => is_scalar($line) ? (string) $line : json_encode($line, JSON_UNESCAPED_UNICODE), $idea));
                }
                $post['design_idea'] = is_string($idea) && trim($idea) !== '' ? trim($idea) : null;
                unset($post['designIdea'], $post['proposed_design']);
                $posts[$i] = $post;
            }
            $plan['posts'] = $posts;
            $plans[$pi] = $plan;
        }

        return array_values($plans);
    }

    /**
     * Second, focused pass for any post the model returned without a design idea,
     * so the "proposed design" box is never silently empty. Best-effort: a failure
     * here never fails the (already paid) generation.
     */
    private function fillMissingDesignIdeas(JsonPrompt $prompt, array $plans, string $mandatory, bool $isCarousel, array $options): array
    {
        $missing = [];
        foreach ($plans as $pi => $plan) {
            foreach ($plan['posts'] ?? [] as $i => $post) {
                if (is_array($post) && empty($post['design_idea'])) {
                    $missing[] = ['ref' => "{$pi}:{$i}", 'headline' => $post['headline'] ?? '', 'content' => $post['content'] ?? '', 'cta' => $post['cta'] ?? ''];
                }
            }
        }
        if ($missing === []) {
            return $plans;
        }

        try {
            $response = $prompt->ask(
                ContentPrompts::designSystem($mandatory, $isCarousel),
                [['role' => 'user', 'content' => json_encode(['posts' => $missing], JSON_UNESCAPED_UNICODE)]],
                $options,
            );
            foreach ($response['result']['posts'] ?? [] as $row) {
                if (! is_array($row) || ! isset($row['ref'])) {
                    continue;
                }
                [$pi, $i] = array_map('intval', explode(':', (string) $row['ref']) + [0, 0]);
                $idea = trim((string) ($row['design_idea'] ?? $row['designIdea'] ?? ''));
                if ($idea !== '' && isset($plans[$pi]['posts'][$i])) {
                    $plans[$pi]['posts'][$i]['design_idea'] = $idea;
                }
            }
        } catch (Throwable) {
            // Keep the generated copy; the UI shows a clear empty-state for this post.
        }

        return $plans;
    }
}
