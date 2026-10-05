<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:190', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            'phone' => ['nullable', 'string', 'max:32'],
            'locale' => ['nullable', 'in:ar,en'],
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
