<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ScheduleRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'post_id' => ['nullable', 'uuid', 'exists:content_posts,id'],
            'brand_id' => ['nullable', 'uuid', 'exists:brands,id'],
            'platform' => ['required', 'string', 'max:64'],
            'social_account_id' => ['nullable', 'uuid', 'exists:social_accounts,id'],
            'caption' => ['nullable', 'string', 'max:20000'],
            'media' => ['nullable', 'array', 'max:10'],
            'media.*' => ['url', 'max:2048'],
            'hashtags' => ['nullable', 'array', 'max:40'],
            'hashtags.*' => ['string', 'max:64'],
            'scheduled_at' => ['required', 'date'],
            'timezone' => ['nullable', 'timezone'],
        ];
    }
}
