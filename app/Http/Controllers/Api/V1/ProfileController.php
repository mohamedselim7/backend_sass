<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    public function update(Request $request): UserResource
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:32'],
            'avatar_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'locale' => ['sometimes', 'in:ar,en'],
        ]);

        $user = $request->user();
        $user->update($data);

        return new UserResource($user->load(['wallet', 'activeSubscription.plan']));
    }

    public function changePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        $user = $request->user();

        if (! Hash::check($data['current_password'], $user->password)) {
            return response()->json([
                'message' => 'كلمة المرور الحالية غير صحيحة.',
                'error' => 'invalid_current_password',
            ], 422);
        }

        $user->update(['password' => $data['password'], 'must_set_password' => false]);

        return response()->json(['message' => 'تم تحديث كلمة المرور.']);
    }
}
