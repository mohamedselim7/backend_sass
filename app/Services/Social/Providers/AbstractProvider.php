<?php

namespace App\Services\Social\Providers;

use App\Exceptions\DomainException;
use App\Services\Social\Contracts\SocialProvider;
use App\Services\Social\DTO\TokenResult;
use Illuminate\Support\Facades\Http;

abstract class AbstractProvider implements SocialProvider
{
    public function __construct(protected readonly array $config) {}

    public function supportsPkce(): bool
    {
        return (bool) ($this->config['pkce'] ?? false);
    }

    public function supportsRefresh(): bool
    {
        return (bool) ($this->config['refresh'] ?? false);
    }

    public function authorizeUrl(array $credentials, string $redirectUri, string $state, ?string $codeChallenge): string
    {
        $params = [
            'response_type' => 'code',
            'client_id' => $credentials['client_id'],
            'redirect_uri' => $redirectUri,
            'scope' => $this->config['scope'],
            'state' => $state,
        ];

        if ($this->supportsPkce() && $codeChallenge) {
            $params['code_challenge'] = $codeChallenge;
            $params['code_challenge_method'] = 'S256';
        }

        return $this->config['authorize_url'].'?'.http_build_query($params);
    }

    public function exchangeCode(array $credentials, string $code, string $redirectUri, ?string $codeVerifier): TokenResult
    {
        $body = [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUri,
            'client_id' => $credentials['client_id'],
            'client_secret' => $credentials['client_secret'],
        ];

        if ($this->supportsPkce() && $codeVerifier) {
            $body['code_verifier'] = $codeVerifier;
        }

        $response = Http::asForm()->timeout(30)->post($this->config['token_url'], $body);

        if ($response->failed() || ! $response->json('access_token')) {
            throw new DomainException(
                $response->json('error_description') ?? $response->json('error.message') ?? 'فشل تبادل رمز الموافقة.',
                502,
                'oauth_exchange_failed',
            );
        }

        return new TokenResult(
            accessToken: (string) $response->json('access_token'),
            refreshToken: $response->json('refresh_token') ? (string) $response->json('refresh_token') : null,
            expiresIn: $response->json('expires_in') ? (int) $response->json('expires_in') : null,
            raw: (array) $response->json(),
        );
    }

    public function refresh(array $credentials, string $refreshToken): ?TokenResult
    {
        if (! $this->supportsRefresh()) {
            return null;
        }

        $response = Http::asForm()->timeout(30)->post($this->config['token_url'], [
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
            'client_id' => $credentials['client_id'],
            'client_secret' => $credentials['client_secret'],
        ]);

        if ($response->failed() || ! $response->json('access_token')) {
            return null;
        }

        return new TokenResult(
            accessToken: (string) $response->json('access_token'),
            refreshToken: $response->json('refresh_token') ? (string) $response->json('refresh_token') : $refreshToken,
            expiresIn: $response->json('expires_in') ? (int) $response->json('expires_in') : null,
            raw: (array) $response->json(),
        );
    }
}
