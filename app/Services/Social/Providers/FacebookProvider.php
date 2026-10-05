<?php

namespace App\Services\Social\Providers;

use App\Exceptions\DomainException;
use App\Models\SocialAccount;
use App\Services\Social\DTO\PublishPayload;
use App\Services\Social\DTO\PublishResult;
use App\Services\Social\DTO\TokenResult;
use Illuminate\Support\Facades\Http;

class FacebookProvider extends AbstractProvider
{
    public function platform(): string
    {
        return 'facebook';
    }

    public function fetchProfile(TokenResult $token): array
    {
        $response = Http::timeout(20)->get('https://graph.facebook.com/v19.0/me', [
            'fields' => 'id,name,picture',
            'access_token' => $token->accessToken,
        ]);

        $data = (array) $response->json();

        return [
            'external_id' => (string) ($data['id'] ?? ''),
            'name' => $data['name'] ?? null,
            'username' => null,
            'avatar_url' => $data['picture']['data']['url'] ?? null,
        ];
    }

    public function publish(SocialAccount $account, PublishPayload $payload): PublishResult
    {
        $endpoint = $payload->media
            ? "https://graph.facebook.com/v19.0/{$account->external_id}/photos"
            : "https://graph.facebook.com/v19.0/{$account->external_id}/feed";

        $body = $payload->media
            ? ['url' => $payload->media[0], 'caption' => $payload->text]
            : ['message' => $payload->text, 'link' => $payload->link];

        $response = Http::asForm()->withToken($account->access_token)->timeout(30)->post($endpoint, array_filter($body));

        if ($response->failed()) {
            throw new DomainException($response->json('error.message') ?? 'فشل النشر على فيسبوك.', 502, 'platform_publish_failed');
        }

        $id = (string) ($response->json('post_id') ?? $response->json('id') ?? '');

        return new PublishResult($id ?: null, $id ? "https://www.facebook.com/{$id}" : null);
    }
}
