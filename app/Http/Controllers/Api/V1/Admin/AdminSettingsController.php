<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppConfig;
use App\Models\ProviderKey;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminSettingsController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'settings' => AppConfig::all()->pluck('value', 'key'),
            'providers' => ProviderKey::all()->map(fn (ProviderKey $key) => [
                'id' => $key->id,
                'provider' => $key->provider,
                'default_model' => $key->default_model,
                'is_active' => $key->is_active,
                'status' => $key->status,
                // The raw key never leaves the server.
                'key_hint' => $key->maskedKey(),
                'verified_at' => $key->verified_at?->toIso8601String(),
            ]),
        ]);
    }

    public function updateSetting(Request $request): JsonResponse
    {
        $data = $request->validate([
            'key' => ['required', 'string', 'max:120'],
            'value' => ['present'],
        ]);

        AppConfig::put($data['key'], $data['value']);

        return response()->json(['message' => 'تم الحفظ.']);
    }

    public function upsertProviderKey(Request $request): JsonResponse
    {
        $data = $request->validate([
            'provider' => ['required', 'string', 'max:64'],
            'api_key' => ['required', 'string', 'max:500'],
            'default_model' => ['nullable', 'string', 'max:120'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        ProviderKey::updateOrCreate(
            ['provider' => $data['provider']],
            $data + ['status' => 'connected', 'verified_at' => now()],
        );

        return response()->json(['message' => 'تم تحديث مفتاح المزود.']);
    }

    public function deleteProviderKey(ProviderKey $providerKey): JsonResponse
    {
        $providerKey->delete();

        return response()->json(['message' => 'تم حذف المفتاح.']);
    }
}
