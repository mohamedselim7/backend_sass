<?php

namespace App\Services\Ai\Prompts;

/**
 * Thin accessor over config('design.formats') — the single source of truth
 * for supported aspect ratios on both the design-generation and raw-image
 * endpoints. Keeping it in one place means the frontend format registry and
 * the backend provider `size` string can never drift apart silently.
 */
final class DesignFormat
{
    public static function default(): string
    {
        return (string) config('design.default_format', '1:1');
    }

    /** @return array<string, array{label:string,width:int,height:int,size:string,css_ratio:string}> */
    public static function all(): array
    {
        return (array) config('design.formats', []);
    }

    public static function ids(): array
    {
        return array_keys(self::all());
    }

    public static function isValid(?string $id): bool
    {
        return $id !== null && array_key_exists($id, self::all());
    }

    /** Resolves a format id to its provider-ready size string, falling back to the square default. */
    public static function resolve(?string $id): array
    {
        $formats = self::all();
        $id = self::isValid($id) ? $id : self::default();

        return $formats[$id] ?? ['label' => 'مربع', 'width' => 1024, 'height' => 1024, 'size' => '1024x1024', 'css_ratio' => '1 / 1'];
    }

    public static function sizeFor(?string $id): string
    {
        return self::resolve($id)['size'];
    }
}
