<?php

namespace App\Services\Social\Providers;

use App\Exceptions\DomainException;
use App\Models\SocialAccount;
use App\Services\Social\DTO\PublishPayload;
use App\Services\Social\DTO\PublishResult;
use App\Services\Social\DTO\TokenResult;
use Illuminate\Support\Facades\Http;

class InstagramProvider extends AbstractProvider
{
    public function platform(): string
    {
        return 'instagram';
    }

    public function fetchProfile(TokenResult $token): array
    {
        $response = Http::timeout(20)->get('https://graph.facebook.com/v19.0/me/accounts', [
            'fields' => 'instagram_business_account{id,name,username,profile_picture_url}',
            'access_token' => $token->accessToken,
        ]);

        $ig = (array) ($response->json('data.0.instagram_business_account') ?? []);

        return [
            'external_id' => (string) ($ig['id'] ?? ''),
            'name' => $ig['name'] ?? null,
            'username' => $ig['username'] ?? null,
            'avatar_url' => $ig['profile_picture_url'] ?? null,
        ];
    }

    public function publish(SocialAccount $account, PublishPayload $payload): PublishResult
    {
        if (! $payload->media) {
            throw new DomainException('يتطلب النشر على إنستغرام صورة واحدة على الأقل.', 422, 'media_required');
        }

        $create = Http::asForm()->withToken($account->access_token)->timeout(30)
            ->post("https://graph.facebook.com/v19.0/{$account->external_id}/media", [
                'image_url' => $payload->media[0],
                'caption' => $payload->text,
            ]);

        if ($create->failed() || ! $create->json('id')) {
            throw new DomainException($create->json('error.message') ?? 'فشل تجهيز منشور إنستغرام.', 502, 'platform_publish_failed');
        }

        $publish = Http::asForm()->withToken($account->access_token)->timeout(30)
            ->post("https://graph.facebook.com/v19.0/{$account->external_id}/media_publish", [
                'creation_id' => $create->json('id'),
            ]);

        if ($publish->failed()) {
            throw new DomainException($publish->json('error.message') ?? 'فشل النشر على إنستغرام.', 502, 'platform_publish_failed');
        }

        $id = (string) ($publish->json('id') ?? '');

        return new PublishResult($id ?: null);
    }
}
