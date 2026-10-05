<?php

/*
 * Per-platform OAuth + publishing configuration. A tenant may override the
 * client id/secret via a `social_apps` row; these are only the system-wide
 * fallback credentials read from the environment.
 */
return [
    'platforms' => [
        'facebook' => [
            'label' => 'Facebook',
            'authorize_url' => 'https://www.facebook.com/v19.0/dialog/oauth',
            'token_url' => 'https://graph.facebook.com/v19.0/oauth/access_token',
            'scope' => 'pages_manage_posts,pages_read_engagement,pages_show_list',
            'pkce' => false,
            'refresh' => true,
            'client_id' => env('SOCIAL_FACEBOOK_APP_ID'),
            'client_secret' => env('SOCIAL_FACEBOOK_APP_SECRET'),
        ],
        'instagram' => [
            'label' => 'Instagram',
            'authorize_url' => 'https://www.facebook.com/v19.0/dialog/oauth',
            'token_url' => 'https://graph.facebook.com/v19.0/oauth/access_token',
            'scope' => 'instagram_basic,instagram_content_publish,pages_show_list',
            'pkce' => false,
            'refresh' => true,
            'client_id' => env('SOCIAL_INSTAGRAM_APP_ID'),
            'client_secret' => env('SOCIAL_INSTAGRAM_APP_SECRET'),
        ],
        'linkedin' => [
            'label' => 'LinkedIn',
            'authorize_url' => 'https://www.linkedin.com/oauth/v2/authorization',
            'token_url' => 'https://www.linkedin.com/oauth/v2/accessToken',
            'scope' => 'openid profile w_member_social',
            'pkce' => false,
            'refresh' => true,
            'client_id' => env('SOCIAL_LINKEDIN_APP_ID'),
            'client_secret' => env('SOCIAL_LINKEDIN_APP_SECRET'),
        ],
        'x' => [
            'label' => 'X (Twitter)',
            'authorize_url' => 'https://twitter.com/i/oauth2/authorize',
            'token_url' => 'https://api.twitter.com/2/oauth2/token',
            'scope' => 'tweet.read tweet.write users.read offline.access',
            'pkce' => true,
            'refresh' => true,
            'client_id' => env('SOCIAL_X_APP_ID'),
            'client_secret' => env('SOCIAL_X_APP_SECRET'),
        ],
    ],
];
