<?php

namespace App\Services;

use App\Models\Media;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** Stores user uploads on the `public` disk (replaces Firebase Storage). */
class MediaService
{
    public const MAX_BYTES = 10 * 1024 * 1024;

    public const ALLOWED_MIMES = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml',
        'application/pdf',
    ];

    /**
     * Served through a Laravel route (not the raw /storage symlink) so CORS
     * middleware actually runs. `php artisan serve` serves an existing
     * public/storage/* file as a static asset and skips the framework
     * entirely, so HandleCors never fires on that path.
     */
    private function urlFor(string $path): string
    {
        return url('api/v1/media-file/'.$path);
    }

    public function store(User $user, UploadedFile $file, ?string $folder = null): Media
    {
        $folder = $folder ? trim($folder, '/') : null;
        $directory = trim('media/'.$user->getKey().($folder ? '/'.$folder : ''), '/');

        $filename = Str::uuid()->toString().'.'.($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
        $path = $file->storeAs($directory, $filename, 'public');

        return Media::create([
            'user_id' => $user->getKey(),
            'disk' => 'public',
            'path' => $path,
            'url' => $this->urlFor($path),
            'mime' => $file->getMimeType(),
            'size' => $file->getSize(),
            'folder' => $folder,
        ]);
    }


    /** Stores raw bytes (e.g. an AI-generated image) on the public disk. */
    public function storeBytes(User $user, string $bytes, string $mime, ?string $folder = null, ?string $extension = null): Media
    {
        $folder = $folder ? trim($folder, '/') : null;
        $directory = trim('media/'.$user->getKey().($folder ? '/'.$folder : ''), '/');

        $extension = $extension ?: match ($mime) {
            'image/png' => 'png',
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            default => 'bin',
        };

        $path = trim($directory.'/'.Str::uuid()->toString().'.'.$extension, '/');

        Storage::disk('public')->put($path, $bytes);

        return Media::create([
            'user_id' => $user->getKey(),
            'disk' => 'public',
            'path' => $path,
            'url' => $this->urlFor($path),
            'mime' => $mime,
            'size' => strlen($bytes),
            'folder' => $folder,
        ]);
    }

    public function delete(Media $media): void
    {
        Storage::disk($media->disk)->delete($media->path);
        $media->delete();
    }
}