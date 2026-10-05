<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\MediaUploadRequest;
use App\Http\Resources\MediaResource;
use App\Models\Media;
use App\Services\MediaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MediaController extends Controller
{
    public function __construct(private readonly MediaService $media) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $items = Media::visibleTo($request->user())
            ->when($request->filled('folder'), fn ($q) => $q->where('folder', $request->string('folder')))
            ->latest()
            ->get();

        return MediaResource::collection($items);
    }

    public function store(MediaUploadRequest $request): JsonResponse
    {
        $media = $this->media->store($request->user(), $request->file('file'), $request->string('folder')->value() ?: null);

        return (new MediaResource($media))->response()->setStatusCode(201);
    }

    public function destroy(Request $request, Media $media): JsonResponse
    {
        $this->authorize('delete', $media);

        $this->media->delete($media);

        return response()->json(['message' => 'تم حذف الملف.']);
    }
}