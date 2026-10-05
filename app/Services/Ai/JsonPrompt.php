<?php

namespace App\Services\Ai;

use App\Exceptions\AiProviderException;
use App\Services\Ai\Contracts\TextProvider;
use App\Services\Ai\DTO\TextResult;

/**
 * Asks a text provider for strict JSON and decodes the reply defensively —
 * models frequently wrap JSON in ```json fences or add stray prose around it.
 */
class JsonPrompt
{
    public function __construct(private readonly TextProvider $provider) {}

    /**
     * @param  array<int, array{role:string,content:string}>  $messages
     * @param  array{model?:string,temperature?:float,max_tokens?:int}  $options
     * @return array{result:array,raw:TextResult}
     *
     * @throws AiProviderException
     */
    public function ask(string $systemPrompt, array $messages, array $options = []): array
    {
        $strictSystemPrompt = trim($systemPrompt."\n\nأجب حصراً بكائن JSON صالح بدون أي نص إضافي أو شروحات أو Markdown.");

        $result = $this->provider->complete($strictSystemPrompt, $messages, $options);

        return [
            'result' => self::decode($result->text, $result->provider),
            'raw' => $result,
        ];
    }

    /** @throws AiProviderException */
    public static function decode(string $text, string $provider = 'unknown'): array
    {
        $cleaned = trim($text);

        // Strip ```json ... ``` or ``` ... ``` code fences.
        if (preg_match('/```(?:json)?\s*(.*?)\s*```/is', $cleaned, $matches)) {
            $cleaned = trim($matches[1]);
        }

        // Fall back to the first {...} or [...] block if there's stray prose around it.
        if (! str_starts_with($cleaned, '{') && ! str_starts_with($cleaned, '[')) {
            if (preg_match('/[\{\[].*[\}\]]/s', $cleaned, $matches)) {
                $cleaned = $matches[0];
            }
        }

        $decoded = json_decode($cleaned, true);

        if (! is_array($decoded) || json_last_error() !== JSON_ERROR_NONE) {
            throw AiProviderException::invalidJson($provider);
        }

        return $decoded;
    }
}
