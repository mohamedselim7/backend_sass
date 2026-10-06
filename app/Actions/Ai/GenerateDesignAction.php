<?php

namespace App\Actions\Ai;

use App\Models\Design;
use App\Models\DesignVersion;
use App\Models\User;
use App\Services\Ai\ProviderResolver;
use App\Services\CreditService;
use App\Services\Ai\Prompts\DesignFormat;
use App\Services\MediaService;
use App\Services\UsageLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Throwable;

class GenerateDesignAction
{
    /** Bumped alongside designSystemPrompt() wording; stored on the Design record. */
    public const VERSION = 'design.v2025-10-06';

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

            $mandatory = ($data['mode'] ?? 'branded') === 'free' ? '' : trim((string) ($data['mandatory_prompt'] ?? ''));
            $result = $textProvider->complete(
                self::designSystemPrompt($mandatory),
                [['role' => 'user', 'content' => self::designUserPrompt($data)]],
                $options,
            );

            // Square (1:1) is the default everywhere a design preview is shown; the
            // registry in config('design.php') is the single source of truth for
            // every supported ratio on both this endpoint and /ai/image.
            $formatId = DesignFormat::isValid($data['format_id'] ?? null) ? $data['format_id'] : DesignFormat::default();
            $imageOptions = $options + ['size' => DesignFormat::sizeFor($formatId)];

            $imageProvider = $this->resolver->image($user, $imageOptions);
            $imageResult = $imageProvider->generate($result->text, $imageOptions);

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

            $design = DB::transaction(function () use ($data, $user, $result, $imageResult, $media, $cost, $remainingCredits, $mandatory, $formatId) {
                $design = Design::create([
                    'user_id' => $user->getKey(),
                    'brand_id' => $data['brand_id'] ?? null,
                    'post_id' => $data['post_id'] ?? null,
                    'headline' => $data['headline'] ?? null,
                    'design_idea' => $result->text,
                    'format' => $data['format'] ?? 'post',
                    'format_id' => $formatId,
                    'prompt_version' => self::VERSION,
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
                $version->setAttribute('applied_mandatory', $mandatory !== '');
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

    /** System prompt for the image brief; the mandatory user prompt is binding when present. */
    public static function designSystemPrompt(string $mandatory): string
    {
        $system = 'You are a senior art director. Turn the request into ONE precise English image-generation prompt for a social-media design: '
            .'subject and scene, composition and focal point, colors and lighting, typography style, and any short on-image Arabic headline in quotes. '
            .'Do not draw any logo (it is added later). Return only the prompt text, no explanations.';

        if ($mandatory !== '') {
            $system .= "\n\nMANDATORY DESIGN PROMPT from the user's Planning & Understanding section. It is BINDING for this design and overrides any conflicting idea; apply every instruction literally:\n<<<\n"
                .$mandatory."\n>>>";
        }

        return $system;
    }

    public static function designUserPrompt(array $data): string
    {
        $parts = ['DESIGN REQUEST: '.$data['prompt']];
        if (! empty($data['content_text'])) {
            $parts[] = 'POST CONTENT (the design must express its main message):'."\n".$data['content_text'];
        }
        if (! empty($data['platform'])) {
            $parts[] = 'PLATFORM: '.$data['platform'];
        }
        if (! empty($data['style_instructions'])) {
            $parts[] = 'STYLE: '.$data['style_instructions'];
        }
        if (! empty($data['negative_instructions'])) {
            $parts[] = 'AVOID: '.$data['negative_instructions'];
        }

        return implode("\n\n", $parts);
    }
}
