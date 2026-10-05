<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\DesignResource;
use App\Http\Resources\DesignVersionResource;
use App\Models\Design;
use App\Models\DesignVersion;
use App\Services\CreditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class DesignController extends Controller
{
    public function __construct(private readonly CreditService $credits) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $designs = Design::visibleTo($request->user())
            ->when($request->filled('brand_id'), fn ($q) => $q->where('brand_id', $request->string('brand_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->with('latestVersion')
            ->latest()
            ->paginate(min($request->integer('per_page', 25), 100));

        return DesignResource::collection($designs);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'post_id' => ['nullable', 'uuid', 'exists:content_posts,id'],
            'brand_id' => ['nullable', 'uuid', 'exists:brands,id'],
            'headline' => ['nullable', 'string', 'max:1000'],
            'description' => ['nullable', 'string', 'max:5000'],
            'design_idea' => ['nullable', 'string', 'max:5000'],
            'format' => ['nullable', 'string', 'max:32'],
            'image_url' => ['nullable', 'url', 'max:2048'],
            'image_storage_path' => ['nullable', 'string', 'max:1024'],
            'provider' => ['nullable', 'string', 'max:64'],
            'model' => ['nullable', 'string', 'max:120'],
        ]);

        $user = $request->user();

        $charge = $this->credits->charge(
            $user,
            'design_generate',
            idempotencyKey: $request->header('Idempotency-Key'),
        );

        try {
            $design = DB::transaction(function () use ($data, $user) {
                $design = Design::create([
                    'user_id' => $user->getKey(),
                    'brand_id' => $data['brand_id'] ?? null,
                    'post_id' => $data['post_id'] ?? null,
                    'headline' => $data['headline'] ?? null,
                    'description' => $data['description'] ?? null,
                    'design_idea' => $data['design_idea'] ?? null,
                    'format' => $data['format'] ?? 'post',
                    'status' => 'generated',
                ]);

                DesignVersion::create([
                    'design_id' => $design->getKey(),
                    'version' => 1,
                    'image_url' => $data['image_url'] ?? null,
                    'image_storage_path' => $data['image_storage_path'] ?? null,
                    'provider' => $data['provider'] ?? null,
                    'model' => $data['model'] ?? null,
                ]);

                return $design;
            });
        } catch (\Throwable $e) {
            $this->credits->refund($user, $charge, 'design creation failed');
            throw $e;
        }

        return (new DesignResource($design->load('versions')))->response()->setStatusCode(201);
    }

    public function show(Request $request, Design $design): DesignResource
    {
        $this->authorize('view', $design);

        return new DesignResource($design->load('versions'));
    }

    /** Adds a revision; the version number is assigned server-side. */
    public function addVersion(Request $request, Design $design): JsonResponse
    {
        $this->authorize('update', $design);

        $data = $request->validate([
            'image_url' => ['nullable', 'url', 'max:2048'],
            'image_storage_path' => ['nullable', 'string', 'max:1024'],
            'instructions' => ['nullable', 'string', 'max:5000'],
            'provider' => ['nullable', 'string', 'max:64'],
            'model' => ['nullable', 'string', 'max:120'],
        ]);

        $user = $request->user();
        $charge = $this->credits->charge($user, 'design_revise', idempotencyKey: $request->header('Idempotency-Key'));

        try {
            $version = DB::transaction(function () use ($design, $data) {
                $next = (int) DesignVersion::where('design_id', $design->getKey())->lockForUpdate()->max('version') + 1;

                return DesignVersion::create($data + ['design_id' => $design->getKey(), 'version' => $next]);
            });
        } catch (\Throwable $e) {
            $this->credits->refund($user, $charge, 'design revision failed');
            throw $e;
        }

        return (new DesignVersionResource($version))->response()->setStatusCode(201);
    }

    public function destroy(Request $request, Design $design): JsonResponse
    {
        $this->authorize('delete', $design);

        $design->delete();

        return response()->json(['message' => 'تم حذف التصميم.']);
    }
}
