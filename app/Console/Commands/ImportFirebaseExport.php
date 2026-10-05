<?php

namespace App\Console\Commands;

use App\Models;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;

/**
 * Imports a Firestore export directory into MySQL.
 *
 * Expected layout: one JSON file per collection, each an object keyed by
 * document id, or an array of documents carrying an `id` field:
 *
 *   php artisan iden:import-firebase storage/app/firebase-export
 *
 * Firestore document ids are preserved as the MySQL uuid keys where they are
 * valid uuids; otherwise a deterministic uuid is derived so repeated runs map
 * the same document to the same row. Every collection is idempotent (upsert).
 */
class ImportFirebaseExport extends Command
{
    protected $signature = 'iden:import-firebase {path} {--dry-run}';

    protected $description = 'Import a Firestore JSON export into the relational database';

    private array $idMap = [];

    public function handle(): int
    {
        $path = rtrim($this->argument('path'), '/');

        if (! is_dir($path)) {
            $this->error("Directory not found: {$path}");

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');

        DB::beginTransaction();

        try {
            $this->importUsers($this->read($path, 'users'));
            $this->importBrands($this->read($path, 'brands'));
            $this->importGenerations($this->read($path, 'content_generations'), $this->read($path, 'content_plans'), $this->read($path, 'content_posts'));
            $this->importSimple($this->read($path, 'goals'), Models\Goal::class, ['title', 'description', 'metric', 'target_value', 'current_value', 'period', 'status']);
            $this->importSimple($this->read($path, 'workspace_records'), Models\WorkspaceRecord::class, ['feature', 'title', 'input', 'result', 'image_storage_path', 'provider', 'model', 'status', 'brand_name']);
            $this->importSimple($this->read($path, 'marketing_angles'), Models\MarketingAngle::class, ['title', 'body', 'category', 'tags', 'understanding', 'status']);
            $this->importSimple($this->read($path, 'prompt_library'), Models\Prompt::class, ['title', 'body', 'category', 'tags', 'is_shared']);
            $this->importSimple($this->read($path, 'notifications'), Models\Notification::class, ['type', 'title', 'body', 'link', 'data', 'read_at']);
            $this->importWallets($this->read($path, 'credit_wallets'));

            if ($dryRun) {
                DB::rollBack();
                $this->warn('Dry run: nothing was written.');
            } else {
                DB::commit();
                $this->info('Import finished.');
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Import failed: '.$e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /** @return array<int, array<string, mixed>> */
    private function read(string $path, string $collection): array
    {
        $file = "{$path}/{$collection}.json";

        if (! is_file($file)) {
            $this->line("skip {$collection} (no file)");

            return [];
        }

        $decoded = json_decode((string) file_get_contents($file), true) ?: [];

        // Accept both "keyed by id" and "list with id field".
        $rows = [];
        foreach ($decoded as $key => $row) {
            if (! is_array($row)) {
                continue;
            }
            $row['id'] = $row['id'] ?? (is_string($key) ? $key : null);
            $rows[] = $row;
        }

        $this->line(sprintf('read %s (%d rows)', $collection, count($rows)));

        return $rows;
    }

    /** Keeps Firestore ids when they are uuids, otherwise derives a stable uuid. */
    private function id(?string $raw, string $namespace): ?string
    {
        if (! $raw) {
            return null;
        }

        $cacheKey = $namespace.':'.$raw;

        return $this->idMap[$cacheKey] ??= Str::isUuid($raw)
            ? $raw
            : Uuid::uuid5(Uuid::NAMESPACE_URL, 'ma3roof:'.$cacheKey)->toString();
    }

    private function importUsers(array $rows): void
    {
        foreach ($rows as $row) {
            $user = Models\User::updateOrCreate(
                ['email' => strtolower((string) ($row['email'] ?? ''))],
                [
                    'id' => $this->id($row['id'] ?? null, 'users'),
                    'name' => $row['displayName'] ?? $row['name'] ?? 'مستخدم',
                    'phone' => $row['phone'] ?? null,
                    'avatar_url' => $row['photoURL'] ?? $row['avatar_url'] ?? null,
                    'locale' => $row['locale'] ?? 'ar',
                    'status' => ($row['disabled'] ?? false) ? 'suspended' : 'active',
                    'firebase_uid' => $row['id'] ?? $row['uid'] ?? null,
                    // Imported accounts set a password on first sign-in.
                    'password' => Str::random(40),
                    'must_set_password' => true,
                ],
            );

            // Only roles that already exist are applied; everyone gets `user`.
            $known = \Spatie\Permission\Models\Role::pluck('name')->all();
            $roles = array_values(array_unique(array_intersect(
                array_merge(['user'], array_map('strval', (array) ($row['roles'] ?? []))),
                $known,
            )));

            $user->syncRoles($roles ?: ['user']);
        }
    }

    private function importBrands(array $rows): void
    {
        foreach ($rows as $row) {
            Models\Brand::updateOrCreate(
                ['id' => $this->id($row['id'] ?? null, 'brands')],
                [
                    'created_by' => $this->userId($row['userId'] ?? $row['created_by'] ?? null),
                    'name' => $row['name'] ?? 'Brand',
                    'industry' => $row['industry'] ?? null,
                    'description' => $row['description'] ?? null,
                    'target_audience' => $row['targetAudience'] ?? null,
                    'target_market' => $row['targetMarket'] ?? null,
                    'tone_of_voice' => $row['toneOfVoice'] ?? null,
                    'content_language' => $row['contentLanguage'] ?? null,
                    'dialect' => $row['dialect'] ?? null,
                    'platforms' => $row['platforms'] ?? [],
                    'logo_url' => $row['logoUrl'] ?? null,
                    'colors' => $row['colors'] ?? [],
                    'fonts' => $row['fonts'] ?? null,
                    'website' => $row['website'] ?? null,
                    'social' => $row['social'] ?? [],
                    'notes' => $row['notes'] ?? null,
                    'is_demo' => (bool) ($row['isDemo'] ?? false),
                ],
            );
        }
    }

    private function importGenerations(array $generations, array $plans, array $posts): void
    {
        foreach ($generations as $row) {
            Models\ContentGeneration::updateOrCreate(
                ['id' => $this->id($row['id'] ?? null, 'content_generations')],
                [
                    'user_id' => $this->userId($row['userId'] ?? null),
                    'brand_id' => $this->id($row['brandId'] ?? null, 'brands'),
                    'brand_name' => $row['brandName'] ?? null,
                    'business_brief' => $row['businessBrief'] ?? null,
                    'monthly_brief' => $row['monthlyBrief'] ?? null,
                    'options' => $row['options'] ?? [],
                    'status' => $row['status'] ?? 'completed',
                    'provider' => $row['provider'] ?? null,
                    'model' => $row['model'] ?? null,
                ],
            );
        }

        foreach ($plans as $index => $row) {
            Models\ContentPlan::updateOrCreate(
                ['id' => $this->id($row['id'] ?? null, 'content_plans')],
                [
                    'generation_id' => $this->id($row['generationId'] ?? null, 'content_generations'),
                    'brand_id' => $this->id($row['brandId'] ?? null, 'brands'),
                    'plan_index' => $row['planIndex'] ?? $index,
                    'name' => $row['name'] ?? 'Plan',
                    'strategy' => $row['strategy'] ?? null,
                    'goal' => $row['goal'] ?? null,
                    'pillars' => $row['pillars'] ?? [],
                    'funnel' => $row['funnel'] ?? [],
                    'formats' => $row['formats'] ?? [],
                ],
            );
        }

        foreach ($posts as $row) {
            Models\ContentPost::updateOrCreate(
                ['id' => $this->id($row['id'] ?? null, 'content_posts')],
                [
                    'plan_id' => $this->id($row['planId'] ?? null, 'content_plans'),
                    'brand_id' => $this->id($row['brandId'] ?? null, 'brands'),
                    'user_id' => $this->userId($row['userId'] ?? null),
                    'post_number' => $row['postNumber'] ?? 1,
                    'content_type' => $row['contentType'] ?? null,
                    'funnel_stage' => $row['funnelStage'] ?? null,
                    'headline' => $row['headline'] ?? null,
                    'content' => $row['content'] ?? null,
                    'cta' => $row['cta'] ?? null,
                    'design_idea' => $row['designIdea'] ?? null,
                    'platform' => $row['platform'] ?? null,
                    'status' => $row['status'] ?? 'generated',
                    'hashtags' => $row['hashtags'] ?? [],
                    'media' => $row['media'] ?? [],
                    'source' => $row['source'] ?? 'ai',
                ],
            );
        }
    }

    private function importSimple(array $rows, string $model, array $fields): void
    {
        foreach ($rows as $row) {
            $attributes = [
                'user_id' => $this->userId($row['userId'] ?? null),
            ];

            if (in_array('brand_name', $fields, true) || array_key_exists('brandId', $row)) {
                $attributes['brand_id'] = $this->id($row['brandId'] ?? null, 'brands');
            }

            foreach ($fields as $field) {
                $camel = Str::camel($field);
                if (array_key_exists($camel, $row) || array_key_exists($field, $row)) {
                    $attributes[$field] = $row[$camel] ?? $row[$field];
                }
            }

            $model::updateOrCreate(
                ['id' => $this->id($row['id'] ?? null, $model)],
                $attributes,
            );
        }
    }

    private function importWallets(array $rows): void
    {
        foreach ($rows as $row) {
            $userId = $this->userId($row['userId'] ?? $row['id'] ?? null);

            if (! $userId) {
                continue;
            }

            Models\CreditWallet::updateOrCreate(
                ['user_id' => $userId],
                [
                    'balance' => (int) ($row['balance'] ?? 0),
                    'lifetime_granted' => (int) ($row['lifetimeGranted'] ?? $row['balance'] ?? 0),
                    'lifetime_spent' => (int) ($row['lifetimeSpent'] ?? 0),
                ],
            );
        }
    }

    private function userId(?string $raw): ?string
    {
        if (! $raw) {
            return null;
        }

        return Models\User::where('firebase_uid', $raw)->value('id') ?? $this->id($raw, 'users');
    }
}
