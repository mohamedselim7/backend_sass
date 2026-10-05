<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

class PasswordController extends Controller
{
    /** Always answers 200 so the endpoint cannot be used to enumerate emails. */
    public function forgot(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        Password::sendResetLink(['email' => $request->string('email')->lower()->value()]);

        return response()->json(['message' => 'إذا كان البريد مسجلاً، سيتم إرسال رابط إعادة التعيين.']);
    }

    public function reset(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => $password,
                    'must_set_password' => false,
                ])->save();
            }
        );

        if ($status !== Password::PasswordReset) {
            return response()->json([
                'message' => 'رابط إعادة التعيين غير صالح أو منتهي.',
                'error' => 'invalid_reset_token',
            ], 422);
        }

        return response()->json(['message' => 'تم تحديث كلمة المرور.']);
    }
}
