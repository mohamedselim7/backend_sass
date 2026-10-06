<?php

return [
    // Default provider/model used when the caller does not choose one explicitly.
    'default_provider' => env('AI_DEFAULT_PROVIDER', 'openai'),
    'default_model' => env('AI_DEFAULT_MODEL', 'gpt-4o-mini'),

    // Internal models an admin can attach to a subscription plan. Users never
    // see or pick these; the backend resolves the model from the user's plan.
    'plan_models' => ['openai', 'gemini', 'nvidia'],

    'providers' => [
        'openai' => [
            'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
            'api_key' => env('OPENAI_API_KEY'),
            'default_model' => env('OPENAI_DEFAULT_MODEL', 'gpt-4o-mini'),
            'image_model' => env('OPENAI_IMAGE_MODEL', 'dall-e-3'),
            // Fallback metadata only: validated against the live /models list before use.
            'models' => ['gpt-4o-mini', 'gpt-4o', 'gpt-4.1'],
            'recommended_models' => ['gpt-4o-mini', 'gpt-4.1-mini'],
        ],
        'openrouter' => [
            'base_url' => env('OPENROUTER_BASE_URL', 'https://openrouter.ai/api/v1'),
            'api_key' => env('OPENROUTER_API_KEY'),
            'default_model' => env('OPENROUTER_DEFAULT_MODEL', 'openai/gpt-4o-mini'),
            'models' => ['openai/gpt-4o-mini', 'anthropic/claude-3.5-sonnet', 'google/gemini-pro-1.5'],
        ],
        'gemini' => [
            'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta/openai'),
            'api_key' => env('GEMINI_API_KEY'),
            'default_model' => env('GEMINI_DEFAULT_MODEL', 'gemini-2.5-flash'),
            'image_model' => env('GEMINI_IMAGE_MODEL', 'gemini-3.1-flash-image'),
            // Fallback metadata only: validated against the live /models list before use.
            'models' => ['gemini-2.5-flash', 'gemini-2.5-pro'],
            'recommended_models' => ['gemini-2.5-flash'],
            'recommended_image_models' => ['gemini-3.1-flash-image', 'gemini-3.1-flash-lite-image', 'gemini-3-pro-image', 'gemini-2.5-flash-image'],
        ],
        'nvidia' => [
            'base_url' => env('NVIDIA_BASE_URL', 'https://integrate.api.nvidia.com/v1'),
            'api_key' => env('NVIDIA_API_KEY'),
            'default_model' => env('NVIDIA_DEFAULT_MODEL', 'openai/gpt-oss-20b'),
            'models' => ['openai/gpt-oss-20b'],
            'max_tokens' => (int) env('NVIDIA_MAX_TOKENS', 4096),
            'reasoning_effort' => env('NVIDIA_REASONING_EFFORT', 'low'),
        ],
    ],

    'timeout' => (int) env('AI_HTTP_TIMEOUT', 60),

    // Live model discovery (OpenAI + Gemini). The provider API is the source of truth.
    'model_discovery' => [
        'enabled' => (bool) env('AI_MODEL_DISCOVERY', true),
        'cache_ttl' => (int) env('AI_MODELS_CACHE_TTL', 3600),
    ],


    // Days usage/activity logs and failed_jobs are kept before pruning.
    'log_retention_days' => env('LOG_RETENTION_DAYS', 180),
];
