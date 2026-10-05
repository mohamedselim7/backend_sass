<?php

namespace App\Actions\Ai;

use App\Models\Design;
use App\Models\DesignVersion;
use App\Models\User;
use App\Services\Ai\ProviderResolver;
use App\Services\CreditService;
use App\Services\MediaService;
use App\Services\UsageLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Throwable;

class GenerateDesignAction
{
    public function __construct(
        private readonly CreditService $credits,
        private readonly UsageLogger $logger,
        private readonly ProviderResolver $resolver,
        private readonly MediaService $media,
    ) {}

    public function execute(User $user, array $data, ?string $idempotencyKey = null): Design
    {
        $cost = $this->credits->cost('design_generate');
        $key = $idempotencyKey ?? 'ai-design:'.$user->getKey().':'.sha1(json_encode($data));

        $charge = $this->credits->charge($user, 'design_generate', $cost, idempotencyKey: $key);

        try {
            $options = array_filter(['provider' => $data['provider'] ?? null, 'model' => $data['model'] ?? null]);
            $textProvider = $this->resolver->text($user, $options);

            $system = 'أنت مصمم إبداعي، تُنتج فكرة تصميم قصيرة وواضحة بناءً على الطلب، دون أي شرح إضافي.';
            $result = $textProvider->complete($system, [['role' => 'user', 'content' => $data['prompt']]], $options);

            $imageProvider = $this->resolver->image($user, $options);
            $imageResult = $imageProvider->generate($result->text, $options);

            if ($imageResult->isUrl) {
                $response = Http::timeout(30)->get($imageResult->base64OrUrl);

                if ($response->failed()) {
                    throw \App\Exceptions\AiProviderException::forProvider(
                        $imageResult->provider,
                        context: ['reason' => 'image_download_failed'],
                    );
                }

                $bytes = $response->body();
            } else {
                $bytes = base64_decode($imageResult->base64OrUrl, true) ?: '';
            }

            $media = $this->media->storeBytes($user, $bytes, $imageResult->mime, 'ai-designs');

            $remainingCredits = (int) $charge->balance_after;

            $design = DB::transaction(function () use ($data, $user, $result, $imageResult, $media, $cost, $remainingCredits) {
                $design = Design::create([
                    'user_id' => $user->getKey(),
                    'brand_id' => $data['brand_id'] ?? null,
                    'post_id' => $data['post_id'] ?? null,
                    'headline' => $data['headline'] ?? null,
                    'design_idea' => $result->text,
                    'format' => $data['format'] ?? 'post',
                    'status' => 'generated',
                ]);

                $version = DesignVersion::create([
                    'design_id' => $design->getKey(),
                    'version' => 1,
                    'image_url' => $media->url,
                    'image_storage_path' => $media->path,
                    'instructions' => $data['prompt'],
                    'provider' => $imageResult->provider,
                    'model' => $imageResult->model,
                ]);

                // Transient — cost/remaining_credits aren't DB columns, just
                // carried on this in-memory instance for the response only.
                $version->setAttribute('cost', $cost);
                $version->setAttribute('remaining_credits', $remainingCredits);

                // Attach the relation manually with this exact instance:
                // $design->load('versions') below would re-query the DB and
                // return a fresh DesignVersion without these attributes.
                $design->setRelation('versions', collect([$version]));
                $design->setRelation('latestVersion', $version);

                return $design;
            });

            $this->logger->usage($user, 'design_generate', ['status' => 'success', 'ref_id' => $design->getKey()]);

            return $design;
        } catch (Throwable $e) {
            $this->credits->refund($user, $charge, 'design generation failed');
            $this->logger->usage($user, 'design_generate', ['status' => 'failed', 'error' => $e->getMessage()]);
            throw $e;
        }
    }
}