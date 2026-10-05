<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\SettingsRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->present($request)]);
    }

    public function update(SettingsRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        if (array_key_exists('notification_preferences', $data)) {
            $data['notification_preferences'] = array_merge(
                $user->notification_preferences ?? [],
                $data['notification_preferences'],
            );
        }

        $user->update($data);

        return response()->json(['data' => $this->present($request)]);
    }

    private function present(Request $request): array
    {
        $user = $request->user();
        $providers = collect(config('ai.providers'))
            ->map(fn (array $cfg, string $key) => [
                'id' => $key,
                'models' => $cfg['models'] ?? [],
                'default_model' => $cfg['default_model'] ?? null,
            ])
            ->values();

        return [
            'locale' => $user->locale,
            'active_model' => $user->active_model,
            'notification_preferences' => $user->notification_preferences ?? [
                'email' => true, 'push' => true, 'marketing' => false,
            ],
            'capabilities' => [
                'default_provider' => config('ai.default_provider'),
                'default_model' => config('ai.default_model'),
                'providers' => $providers,
            ],
        ];
    }
}
