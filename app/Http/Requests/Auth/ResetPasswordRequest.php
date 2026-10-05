<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ResetPasswordRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ];
    }

    public function messages(): array
    {
        $weak = 'ادخل كلمة سر قوية: 8 أحرف على الأقل وتحتوي على حروف وأرقام.';

        return [
            'password.required' => $weak,
            'password.min' => $weak,
            'password.letters' => $weak,
            'password.numbers' => $weak,
            'password.confirmed' => 'كلمتا المرور غير متطابقتين.',
            'email.unique' => 'البريد الإلكتروني مسجّل بالفعل.',
            'email.email' => 'البريد الإلكتروني غير صحيح.',
        ];
    }
}
