<?php

namespace App\Services\Angles;

use App\Models\MarketingAngle;

/** Maps a stored angle to the shape the app's angles library expects (camelCase). */
final class AngleLibraryPresenter
{
    /** Text fields kept inside the `details` JSON column. */
    public const TEXT_FIELDS = [
        'topic', 'perspective', 'audience', 'tension', 'objective', 'beliefShift',
        'example', 'hook', 'proofRequirements', 'understandingSnapshot', 'understandingHash',
    ];

    /** @return array<string, mixed> */
    public static function present(MarketingAngle $angle): array
    {
        $d = is_array($angle->details) ? $angle->details : [];
        $text = fn (string $key) => (string) ($d[$key] ?? '');
        $list = fn (string $key) => array_values(array_filter(array_map('strval', (array) ($d[$key] ?? [])), 'strlen'));

        return [
            'id' => $angle->getKey(),
            'expertiseId' => (string) $angle->expertise_id,
            'name' => $angle->title,
            'topic' => $text('topic') ?: (string) $angle->category,
            'perspective' => $text('perspective'),
            'audience' => $text('audience'),
            'tension' => $text('tension'),
            'objective' => $text('objective'),
            'beliefShift' => $text('beliefShift'),
            'keyMessage' => (string) $angle->body,
            'example' => $text('example'),
            'hook' => $text('hook'),
            'formats' => array_values((array) ($angle->tags ?? [])),
            'guidingQuestions' => $list('guidingQuestions'),
            'proofRequirements' => $text('proofRequirements'),
            'needsVerification' => (bool) ($d['needsVerification'] ?? false),
            'references' => $list('references'),
            'source' => $angle->source === 'manual' ? 'manual' : 'ai',
            'editedByUser' => (bool) $angle->edited_by_user,
            'status' => in_array($angle->status, ['draft', 'approved', 'archived'], true) ? $angle->status : 'draft',
            'createdAt' => $angle->created_at?->toIso8601String(),
            'updatedAt' => $angle->updated_at?->toIso8601String(),
            'usageCount' => (int) $angle->usage_count,
            'lastUsedAt' => $angle->last_used_at?->toIso8601String(),
            'understandingSnapshot' => $text('understandingSnapshot'),
            'understandingHash' => $text('understandingHash'),
        ];
    }
}
