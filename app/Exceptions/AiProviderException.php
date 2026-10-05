<?php

namespace App\Exceptions;

class AiProviderException extends DomainException
{
    public function __construct(string $message, string $provider, int $status = 502, array $context = [])
    {
        parent::__construct(
            $message,
            $status,
            'ai_provider_error',
            array_merge(['provider' => $provider], $context),
        );
    }

    /** Wraps an unexpected/low-level failure with a safe Arabic message; never includes the API key. */
    public static function forProvider(string $provider, string $safeMessage = 'تعذر التواصل مع مزود الذكاء الاصطناعي، حاول مرة أخرى.', int $status = 502, array $context = []): self
    {
        return new self($safeMessage, $provider, $status, $context);
    }

    /** True when the provider rejected the request because the model does not exist / is not usable. */
    public function isModelUnavailable(): bool
    {
        return ($this->context['reason'] ?? null) === 'model_unavailable';
    }

    public static function invalidJson(string $provider): self
    {
        return new self('تعذرت معالجة استجابة مزود الذكاء الاصطناعي.', $provider, 502, ['reason' => 'invalid_json']);
    }

    public static function noKeyConfigured(string $provider): self
    {
        return new self('لا يوجد مفتاح API مُفعّل لهذا المزود.', $provider, 422, ['reason' => 'missing_api_key']);
    }

    public static function unknownProvider(string $provider): self
    {
        return new self('مزود الذكاء الاصطناعي غير مدعوم.', $provider, 422, ['reason' => 'unknown_provider']);
    }
}
