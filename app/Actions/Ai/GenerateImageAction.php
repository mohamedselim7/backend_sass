<?php

namespace App\Actions\Ai;

use App\Models\Media;
use App\Models\User;
use App\Services\Ai\ProviderResolver;
use App\Services\CreditService;
use App\Services\Ai\Prompts\DesignFormat;
use App\Services\MediaService;
use App\Services\UsageLogger;
use Illuminate\Support\Facades\Http;
use Throwable;

class GenerateImageAction
{
    public function __construct(
        private readonly CreditService $credits,
        private readonly UsageLogger $logger,
        private readonly ProviderResolver $resolver,
        private readonly MediaService $media,
    ) {}

    public function execute(User $user, array $data, ?string $idempotencyKey = null): Media
    {
        $cost = $this->credits->cost('image_generate');
        $key = $idempotencyKey ?? 'ai-image:'.$user->getKey().':'.sha1(json_encode($data));

        $charge = $this->credits->charge($user, 'image_generate', $cost, idempotencyKey: $key);

        try {
            $options = array_filter([
                'provider' => $data['provider'] ?? null,
                'model' => $data['model'] ?? null,
                'size' => $data['size'] ?? (isset($data['format_id']) ? DesignFormat::sizeFor($data['format_id']) : null),
            ]);

            $imageProvider = $this->resolver->image($user, $options);
            $result = $imageProvider->generate($data['prompt'], $options);

            if ($result->isUrl) {
                $response = Http::timeout(30)->get($result->base64OrUrl);

                if ($response->failed()) {
                    throw \App\Exceptions\AiProviderException::forProvider(
                        $result->provider,
                        context: ['reason' => 'image_download_failed'],
                    );
                }

                $bytes = $response->body();
            } else {
                $bytes = base64_decode($result->base64OrUrl, true) ?: '';
            }

            $media = $this->media->storeBytes(
                $user,
                $bytes,
                $result->mime,
                $data['folder'] ?? 'ai-images',
            );

            $this->logger->usage($user, 'image_generate', [
                'status' => 'success',
                'ref_id' => $media->getKey(),
                'provider' => $result->provider,
                'model' => $result->model,
            ]);

            return $media;
        } catch (Throwable $e) {
            $this->credits->refund($user, $charge, 'image generation failed');
            $this->logger->usage($user, 'image_generate', ['status' => 'failed', 'error' => $e->getMessage()]);
            throw $e;
        }
    }
}
