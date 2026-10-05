<?php

namespace App\Services\Social\Contracts;

use App\Models\SocialAccount;
use App\Services\Social\DTO\PublishPayload;
use App\Services\Social\DTO\PublishResult;
use App\Services\Social\DTO\TokenResult;

interface SocialProvider
{
    public function platform(): string;

    public function supportsPkce(): bool;

    public function supportsRefresh(): bool;

    /** @param array<string,string> $credentials client_id/client_secret */
    public function authorizeUrl(array $credentials, string $redirectUri, string $state, ?string $codeChallenge): string;

    /** @param array<string,string> $credentials client_id/client_secret */
    public function exchangeCode(array $credentials, string $code, string $redirectUri, ?string $codeVerifier): TokenResult;

    /** @param array<string,string> $credentials client_id/client_secret */
    public function refresh(array $credentials, string $refreshToken): ?TokenResult;

    /** @return array{external_id:string,name:?string,username:?string,avatar_url:?string} */
    public function fetchProfile(TokenResult $token): array;

    public function publish(SocialAccount $account, PublishPayload $payload): PublishResult;
}
