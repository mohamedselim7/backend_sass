<?php

return [
    // Which gateway PaymentService resolves. 'none' records payments without charging.
    'gateway' => env('PAYMENT_GATEWAY', 'none'),

    'currency' => env('PAYMENT_CURRENCY', 'EGP'),

    'gateways' => [
        'paymob' => [
            'api_key' => env('PAYMOB_API_KEY'),
            'integration_id' => env('PAYMOB_INTEGRATION_ID'),
            'iframe_id' => env('PAYMOB_IFRAME_ID'),
            'hmac' => env('PAYMOB_HMAC_SECRET'),
        ],
        'easykash' => [
            'base_url' => env('EASYKASH_BASE_URL', 'https://back.easykash.net'),
            'api_key' => env('EASYKASH_API_KEY'),
            'hmac' => env('EASYKASH_HMAC_SECRET'),
            // Where EasyKash sends the customer after paying (the React return page).
            'redirect_url' => env('EASYKASH_REDIRECT_URL', rtrim((string) env('FRONTEND_URL', 'http://localhost:5173'), '/').'/payment/return'),
            // Optional list of EasyKash payment option ids, e.g. "2,4,5". Empty = all enabled on the account.
            'payment_options' => array_values(array_filter(array_map('intval', explode(',', (string) env('EASYKASH_PAYMENT_OPTIONS', ''))))),
            'cash_expiry_hours' => (int) env('EASYKASH_CASH_EXPIRY_HOURS', 3),
        ],
    ],

    // Credits charged per feature run. Overridable at runtime via the
    // `credit_costs` key in app_configs (admin settings screen).
    'credit_costs' => [
        'default' => 1,
        'content_plan' => 10,
        'content_post' => 1,
        'design_generate' => 5,
        'design_revise' => 3,
        'marketing_angle' => 4,
        'chat_message' => 1,
        'image_generate' => 5,
        'reels_generate' => 6,
    ],

    // Credits granted on signup.
    'signup_credits' => (int) env('SIGNUP_CREDITS', 20),
];
