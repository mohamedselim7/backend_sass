<?php

namespace App\Actions\Ai;

use App\Models\MarketingAngle;
use App\Models\User;
use App\Services\Ai\Contracts\TextProvider;
use App\Services\Ai\JsonPrompt;
use App\Services\Ai\ProviderResolver;
use App\Services\CreditService;
use App\Services\UsageLogger;
use Illuminate\Support\Collection;
use Throwable;

class GenerateAnglesAction
{
    public function __construct(
        private readonly CreditService $credits,
        private readonly UsageLogger $logger,
        private readonly ProviderResolver $resolver,
    ) {}

    /** @return Collection<int, MarketingAngle> */
    public function execute(User $user, array $data, ?string $idempotencyKey = null): Collection
    {
        $count = max(1, (int) ($data['count'] ?? 5));
        $cost = $this->credits->cost('marketing_angle') * $count;
        $key = $idempotencyKey ?? 'ai-angles:'.$user->getKey().':'.sha1(json_encode($data));

        $charge = $this->credits->charge($user, 'marketing_angle', $cost, idempotencyKey: $key);

        try {
            $options = array_filter(['provider' => $data['provider'] ?? null, 'model' => $data['model'] ?? null]);
            $textProvider = $this->resolver->text($user, $options);
            $prompt = new JsonPrompt($textProvider);

            $system = 'أنت خبير تسويق يولّد زوايا تسويقية. أرجع JSON فقط بالشكل: '
                .'{"angles":[{"title":string,"body":string,"category":string,"tags":array}]}';

            $message = sprintf("الموضوع: %s\nعدد الزوايا المطلوب: %d", $data['topic'], $count);

            $result = $prompt->ask($system, [['role' => 'user', 'content' => $message]], $options)['result'];

            $angles = $result['angles'] ?? $result;
            $angles = array_slice(is_array($angles) ? array_values($angles) : [], 0, $count);

            $rows = collect($angles)->map(function (array $angle) use ($user, $data) {
                return MarketingAngle::create([
                    'user_id' => $user->getKey(),
                    'brand_id' => $data['brand_id'] ?? null,
                    'title' => $angle['title'] ?? 'زاوية تسويقية',
                    'body' => $angle['body'] ?? null,
                    'category' => $angle['category'] ?? null,
                    'tags' => $angle['tags'] ?? [],
                    'understanding' => ['topic' => $data['topic']],
                    'status' => 'ready',
                ]);
            });

            $this->logger->usage($user, 'marketing_angle', ['status' => 'success', 'details' => ['count' => $rows->count()]]);

            return $rows;
        } catch (Throwable $e) {
            $this->credits->refund($user, $charge, 'angle generation failed');
            $this->logger->usage($user, 'marketing_angle', ['status' => 'failed', 'error' => $e->getMessage()]);
            throw $e;
        }
    }
}
