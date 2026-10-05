<?php

namespace App\Services\Ai\Contracts;

use App\Services\Ai\DTO\TextResult;
use App\Exceptions\AiProviderException;

interface TextProvider
{
    /**
     * @param  array<int, array{role:string,content:string}>  $messages
     * @param  array{model?:string,temperature?:float,max_tokens?:int}  $options
     *
     * @throws AiProviderException
     */
    public function complete(string $systemPrompt, array $messages, array $options = []): TextResult;
}
