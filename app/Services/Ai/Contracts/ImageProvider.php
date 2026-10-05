<?php

namespace App\Services\Ai\Contracts;

use App\Services\Ai\DTO\ImageResult;
use App\Exceptions\AiProviderException;

interface ImageProvider
{
    /**
     * @param  array{model?:string,size?:string,quality?:string}  $options
     *
     * @throws AiProviderException
     */
    public function generate(string $prompt, array $options = []): ImageResult;
}
