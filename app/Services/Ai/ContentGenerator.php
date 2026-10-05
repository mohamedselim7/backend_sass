<?php

namespace App\Services\Ai;

use App\Models\User;
use App\Services\Ai\Contracts\ContentGeneratorInterface;
use App\Services\Ai\ProviderResolver;

/**
 * Real implementation of ContentGeneratorInterface backed by the configured
 * text provider. Used both synchronously (AiController) and from the queued
 * GenerateContentJob.
 */
class ContentGenerator implements ContentGeneratorInterface
{
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

        $planCount = (int) ($input['plan_count'] ?? 1);
        $postsPerPlan = (int) ($input['posts_per_plan'] ?? 5);
        $brief = $input['brief'] ?? $input['business_brief'] ?? $input['monthly_brief'] ?? '';
        $platforms = $input['platforms'] ?? [];

        $system = 'أنت مساعد تسويق يبني خطط محتوى. أرجع كائن JSON بالشكل التالي فقط: '
            .'{"plans":[{"name":string,"strategy":string,"goal":string,"pillars":array,"funnel":array,'
            .'"formats":array,"posts":[{"headline":string,"content":string,"cta":string,"platform":string,'
            .'"content_type":string,"funnel_stage":string,"hashtags":array}]}]}';

        $message = sprintf(
            "الوصف: %s\nالمنصات: %s\nعدد الخطط المطلوبة: %d\nعدد المنشورات في كل خطة: %d",
            $brief,
            implode(', ', $platforms),
            max(1, $planCount),
            max(1, $postsPerPlan),
        );

        $response = $prompt->ask($system, [['role' => 'user', 'content' => $message]], $options);

        $plans = $response['result']['plans'] ?? $response['result'] ?? [];

        return array_values(is_array($plans) ? $plans : []);
    }
}
