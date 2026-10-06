<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\MarketingAngle;
use App\Models\MarketingAngleUsage;
use App\Services\Ai\JsonPrompt;
use App\Services\Ai\ProviderResolver;
use App\Services\Angles\AngleLibraryPresenter;
use App\Services\CreditService;
use App\Services\UsageLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * DEPRECATED (spec #23): "Marketing angles library" has been removed from the
 * user-facing product. All routes pointing to this controller are disabled in
 * routes/v1/workspace.php. Kept in place (unrouted) so existing data and any
 * future admin-only tooling can still rely on `MarketingAngle`/`MarketingAngleUsage`
 * without a destructive migration. Do not wire this controller back into
 * user-facing routes without explicit product approval.
 */
class MarketingAngleController extends Controller
{
    public function __construct(
        private readonly CreditService $credits,
        private readonly UsageLogger $logger,
        private readonly ProviderResolver $resolver,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['expertise_id' => ['required', 'string', 'max:120']]);

        $angles = MarketingAngle::query()
            ->where('user_id', $request->user()->getKey())
            ->where('expertise_id', $data['expertise_id'])
            ->latest()
            ->get()
            ->map(fn (MarketingAngle $angle) => AngleLibraryPresenter::present($angle))
            ->values();

        return response()->json(['data' => $angles]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id' => ['nullable', 'uuid'],
            'expertise_id' => ['required', 'string', 'max:120'],
            'name' => ['required', 'string', 'max:255'],
            'key_message' => ['nullable', 'string', 'max:4000'],
            'formats' => ['nullable', 'array', 'max:20'],
            'formats.*' => ['string', 'max:80'],
            'guiding_questions' => ['nullable', 'array', 'max:20'],
            'guiding_questions.*' => ['string', 'max:500'],
            'references' => ['nullable', 'array', 'max:30'],
            'references.*' => ['string', 'max:1000'],
            'needs_verification' => ['nullable', 'boolean'],
            ...collect(['topic', 'perspective', 'audience', 'tension', 'objective', 'belief_shift', 'example', 'hook', 'proof_requirements'])
                ->mapWithKeys(fn ($f) => [$f => ['nullable', 'string', 'max:4000']])->all(),
        ], ['name.required' => 'اكتب اسم الزاوية.']);

        $user = $request->user();
        $angle = isset($data['id'])
            ? MarketingAngle::where('user_id', $user->getKey())->findOrFail($data['id'])
            : new MarketingAngle(['user_id' => $user->getKey(), 'source' => 'manual', 'status' => 'draft']);

        $details = array_merge(is_array($angle->details) ? $angle->details : [], [
            'topic' => $data['topic'] ?? '',
            'perspective' => $data['perspective'] ?? '',
            'audience' => $data['audience'] ?? '',
            'tension' => $data['tension'] ?? '',
            'objective' => $data['objective'] ?? '',
            'beliefShift' => $data['belief_shift'] ?? '',
            'example' => $data['example'] ?? '',
            'hook' => $data['hook'] ?? '',
            'proofRequirements' => $data['proof_requirements'] ?? '',
            'guidingQuestions' => $data['guiding_questions'] ?? [],
            'references' => $data['references'] ?? [],
            'needsVerification' => (bool) ($data['needs_verification'] ?? false),
        ]);

        $angle->fill([
            'expertise_id' => $data['expertise_id'],
            'title' => $data['name'],
            'body' => $data['key_message'] ?? '',
            'category' => $data['topic'] ?? null,
            'tags' => $data['formats'] ?? [],
            'details' => $details,
            'edited_by_user' => $angle->exists ? true : $angle->edited_by_user,
        ])->save();

        return response()->json(['data' => AngleLibraryPresenter::present($angle->refresh())]);
    }

    public function status(Request $request, string $angle): JsonResponse
    {
        $data = $request->validate(['status' => ['required', 'in:draft,approved,archived']]);
        $row = MarketingAngle::where('user_id', $request->user()->getKey())->findOrFail($angle);
        $row->update(['status' => $data['status']]);

        return response()->json(['ok' => true]);
    }

    public function recordUsage(Request $request, string $angle): JsonResponse
    {
        $data = $request->validate([
            'generation_id' => ['required', 'string', 'max:120'],
            'brief_excerpt' => ['nullable', 'string', 'max:2000'],
        ]);
        $row = MarketingAngle::where('user_id', $request->user()->getKey())->findOrFail($angle);

        MarketingAngleUsage::create([
            'marketing_angle_id' => $row->getKey(),
            'user_id' => $request->user()->getKey(),
            'generation_id' => $data['generation_id'],
            'brief_excerpt' => $data['brief_excerpt'] ?? '',
            'angle_snapshot' => AngleLibraryPresenter::present($row),
        ]);
        $row->forceFill(['usage_count' => $row->usage_count + 1, 'last_used_at' => now()])->save();

        return response()->json(['ok' => true]);
    }

    public function usages(Request $request, string $angle): JsonResponse
    {
        $row = MarketingAngle::where('user_id', $request->user()->getKey())->findOrFail($angle);

        $usages = MarketingAngleUsage::where('marketing_angle_id', $row->getKey())
            ->latest()->limit(20)->get()
            ->map(fn (MarketingAngleUsage $u) => [
                'id' => $u->getKey(),
                'generationId' => (string) $u->generation_id,
                'briefExcerpt' => (string) $u->brief_excerpt,
                'createdAt' => $u->created_at?->toIso8601String(),
                'angleSnapshot' => $u->angle_snapshot ?? [],
            ])->values();

        return response()->json(['data' => $usages]);
    }

    /** One credit charge per generation batch (cost of the `marketing_angle` feature). */
    public function generate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'expertise_id' => ['required', 'string', 'max:120'],
            'understanding' => ['required', 'array'],
            'understanding.*' => ['nullable', 'string', 'max:6000'],
            'count' => ['nullable', 'integer', 'min:1', 'max:20'],
            'topic_focus' => ['nullable', 'string', 'max:500'],
        ]);

        $user = $request->user();
        $count = (int) ($data['count'] ?? 12);
        $u = $data['understanding'];
        $understandingText = collect([
            'النشاط والخبرة' => $u['business'] ?? '',
            'الجمهور' => $u['audience'] ?? '',
            'محاور المحتوى' => $u['topics'] ?? '',
            'أهداف المحتوى' => $u['goal'] ?? '',
            'نبرة العلامة' => $u['tone'] ?? '',
            'مراجع' => $u['references'] ?? '',
        ])->filter(fn ($v) => trim((string) $v) !== '')->map(fn ($v, $k) => "{$k}: {$v}")->implode("\n");

        $charge = $this->credits->charge($user, 'marketing_angle', idempotencyKey: $request->header('Idempotency-Key'));

        try {
            $system = 'أنت استراتيجي محتوى. ولّد زوايا تسويقية (منظور لمعالجة موضوع، وليست هوكًا ولا فورمات). '
                .'أرجع JSON بالشكل: {"angles":[{"name":string,"topic":string,"perspective":string,"audience":string,'
                .'"tension":string,"objective":string,"beliefShift":string,"keyMessage":string,"example":string,"hook":string,'
                .'"formats":string[],"guidingQuestions":string[],"proofRequirements":string,"needsVerification":boolean}]}';
            $message = "بيانات الفهم:\n{$understandingText}\n\nعدد الزوايا: {$count}"
                .(! empty($data['topic_focus']) ? "\nركّز على الموضوع: {$data['topic_focus']}" : '');

            $prompt = new JsonPrompt($this->resolver->text($user));
            $result = $prompt->ask($system, [['role' => 'user', 'content' => $message]])['result'];
            $rows = array_slice(array_values((array) ($result['angles'] ?? $result)), 0, $count);

            $existing = MarketingAngle::where('user_id', $user->getKey())
                ->where('expertise_id', $data['expertise_id'])
                ->pluck('title')->map(fn ($t) => mb_strtolower(trim((string) $t)))->all();

            $created = collect();
            $skipped = 0;
            foreach ($rows as $item) {
                if (! is_array($item)) { $skipped++; continue; }
                $name = trim((string) ($item['name'] ?? ''));
                if ($name === '' || in_array(mb_strtolower($name), $existing, true)) { $skipped++; continue; }
                $existing[] = mb_strtolower($name);

                $details = [];
                foreach (AngleLibraryPresenter::TEXT_FIELDS as $field) {
                    $details[$field] = (string) ($item[$field] ?? '');
                }
                $details['understandingSnapshot'] = mb_substr($understandingText, 0, 2000);
                $details['understandingHash'] = sha1($understandingText);
                $details['guidingQuestions'] = array_values((array) ($item['guidingQuestions'] ?? []));
                $details['references'] = [];
                $details['needsVerification'] = (bool) ($item['needsVerification'] ?? false);

                $created->push(MarketingAngle::create([
                    'user_id' => $user->getKey(),
                    'expertise_id' => $data['expertise_id'],
                    'title' => mb_substr($name, 0, 255),
                    'body' => (string) ($item['keyMessage'] ?? ''),
                    'category' => mb_substr((string) ($item['topic'] ?? ''), 0, 255) ?: null,
                    'tags' => array_values((array) ($item['formats'] ?? [])),
                    'understanding' => $u,
                    'details' => $details,
                    'source' => 'ai',
                    'status' => 'draft',
                ]));
            }

            $this->logger->usage($user, 'marketing_angle', ['status' => 'success', 'details' => ['count' => $created->count()]]);

            return response()->json([
                'created' => $created->count(),
                'skipped' => $skipped,
                'angles' => $created->map(fn ($a) => AngleLibraryPresenter::present($a))->values(),
            ]);
        } catch (Throwable $e) {
            $this->credits->refund($user, $charge, 'angle library generation failed');
            $this->logger->usage($user, 'marketing_angle', ['status' => 'failed', 'error' => $e->getMessage()]);
            throw $e;
        }
    }
}
