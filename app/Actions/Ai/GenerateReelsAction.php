<?php

namespace App\Actions\Ai;

use App\Models\User;
use App\Services\Ai\JsonPrompt;
use App\Services\Ai\ProviderResolver;
use App\Services\CreditService;
use App\Services\UsageLogger;
use Throwable;

/**
 * Generates a reels plan/scripts via a JSON-strict text prompt. Response
 * shape matches docs/CONTRACT.md exactly:
 * { result: { planName?, strategy?, reels: [{ title?, hook?, fullScript?, caption?, sources? }] } }
 */
class GenerateReelsAction
{
    public function __construct(
        private readonly CreditService $credits,
        private readonly UsageLogger $logger,
        private readonly ProviderResolver $resolver,
    ) {}

    public function execute(User $user, array $data, ?string $idempotencyKey = null): array
    {
        $count = max(1, (int) ($data['count'] ?? 1));
        $cost = $this->credits->cost('reels_generate') * $count;
        $key = $idempotencyKey ?? 'ai-reels:'.$user->getKey().':'.sha1(json_encode($data));

        $charge = $this->credits->charge($user, 'reels_generate', $cost, idempotencyKey: $key);

        try {
            $options = array_filter(['provider' => $data['provider'] ?? null, 'model' => $data['model'] ?? null]);
            $textProvider = $this->resolver->text($user, $options);
            $prompt = new JsonPrompt($textProvider);

            $system = 'أنت خبير محتوى ريلز على السوشيال ميديا. أرجع JSON فقط بالشكل التالي: '
                .'{"planName":string,"strategy":string,"reels":[{"title":string,"hook":string,'
                .'"fullScript":string,"caption":string,"sources":array}]}';

            $message = sprintf(
                "الوضع: %s\nالوصف/الموجز: %s\nعدد الريلز المطلوب: %d\nالمنصة: %s\nالمدة (ثانية): %s\nاللغة: %s\nاللهجة: %s\nالنبرة: %s\nالجمهور: %s\nالهدف: %s",
                $data['mode'] ?? 'plan',
                $data['brief'] ?? $data['prompt'] ?? '',
                $count,
                $data['platform'] ?? 'general',
                $data['duration'] ?? 'n/a',
                $data['language'] ?? 'ar',
                $data['dialect'] ?? 'n/a',
                $data['tone'] ?? 'n/a',
                $data['audience'] ?? 'n/a',
                $data['goal'] ?? 'n/a',
            );

            $response = $prompt->ask($system, [['role' => 'user', 'content' => $message]], $options)['result'];

            $reels = $response['reels'] ?? [];
            $reels = array_slice(is_array($reels) ? array_values($reels) : [], 0, $count);

            $reels = array_map(static function ($reel) {
                $reel = is_array($reel) ? $reel : [];

                return array_filter([
                    'title' => $reel['title'] ?? null,
                    'hook' => $reel['hook'] ?? null,
                    'fullScript' => $reel['fullScript'] ?? $reel['full_script'] ?? null,
                    'caption' => $reel['caption'] ?? null,
                    'sources' => $reel['sources'] ?? null,
                ], static fn ($v) => $v !== null);
            }, $reels);

            $result = array_filter([
                'planName' => $response['planName'] ?? $response['plan_name'] ?? null,
                'strategy' => $response['strategy'] ?? null,
                'reels' => $reels,
            ], static fn ($v) => $v !== null);

            $result['reels'] = $reels;

            $this->logger->usage($user, 'reels_generate', ['status' => 'success', 'details' => ['count' => count($reels)]]);

            return $result;
        } catch (Throwable $e) {
            $this->credits->refund($user, $charge, 'reels generation failed');
            $this->logger->usage($user, 'reels_generate', ['status' => 'failed', 'error' => $e->getMessage()]);
            throw $e;
        }
    }
}
