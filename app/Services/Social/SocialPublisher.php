<?php

namespace App\Services\Social;

use App\Exceptions\DomainException;
use App\Models\ScheduledPost;

/**
 * Sends a scheduled post to the target network. Each platform driver is
 * intentionally small: it only knows how to turn a ScheduledPost into an API
 * call and return the created post id.
 */
class SocialPublisher
{
    /** @return array{external_post_id:?string} */
    public function publish(ScheduledPost $scheduled): array
    {
        $account = $scheduled->socialAccount;

        if (! $account || $account->status !== 'connected') {
            throw new DomainException('لا يوجد حساب متصل لهذه المنصة.', 422, 'social_account_missing');
        }

        return match ($scheduled->platform) {
            'facebook', 'instagram' => $this->publishToMeta($scheduled, $account->access_token, $account->external_id),
            default => throw new DomainException(
                "النشر التلقائي غير مدعوم على {$scheduled->platform} بعد.",
                422,
                'platform_unsupported',
            ),
        };
    }

    /** @return array{external_post_id:?string} */
    protected function publishToMeta(ScheduledPost $scheduled, string $token, string $pageId): array
    {
        $media = $scheduled->media ?? [];
        $message = trim(($scheduled->caption ?? '').' '.collect($scheduled->hashtags ?? [])
            ->map(fn ($tag) => str_starts_with($tag, '#') ? $tag : '#'.$tag)->implode(' '));

        $endpoint = $media
            ? "https://graph.facebook.com/v19.0/{$pageId}/photos"
            : "https://graph.facebook.com/v19.0/{$pageId}/feed";

        $payload = $media
            ? ['url' => $media[0], 'caption' => $message]
            : ['message' => $message];

        $response = \Illuminate\Support\Facades\Http::asForm()
            ->withToken($token)
            ->timeout(30)
            ->post($endpoint, $payload);

        if ($response->failed()) {
            throw new DomainException(
                $response->json('error.message') ?? 'فشل النشر على المنصة.',
                502,
                'platform_publish_failed',
            );
        }

        return ['external_post_id' => (string) ($response->json('post_id') ?? $response->json('id') ?? '')];
    }
}
