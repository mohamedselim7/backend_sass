<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Queue Connection Name
    |--------------------------------------------------------------------------
    |
    | Falls back to the database driver so a fresh environment keeps working
    | without Redis configured. Set QUEUE_CONNECTION=redis in production.
    |
    */

    'default' => env('QUEUE_CONNECTION', 'database'),

    'connections' => [

        'sync' => [
            'driver' => 'sync',
        ],

        'database' => [
            'driver' => 'database',
            'connection' => env('DB_QUEUE_CONNECTION'),
            'table' => env('DB_QUEUE_TABLE', 'jobs'),
            'queue' => env('DB_QUEUE', 'default'),
            'retry_after' => (int) env('DB_QUEUE_RETRY_AFTER', 90),
            'after_commit' => true,
        ],

        'redis' => [
            'driver' => 'redis',
            'connection' => env('REDIS_QUEUE_CONNECTION', 'default'),
            'queue' => env('REDIS_QUEUE', 'default'),
            'retry_after' => (int) env('REDIS_QUEUE_RETRY_AFTER', 90),
            'block_for' => null,
            'after_commit' => true,
        ],

    ],

    'batching' => [
        'database' => env('DB_CONNECTION', 'mysql'),
        'table' => 'job_batches',
    ],

    'failed' => [
        'driver' => env('QUEUE_FAILED_DRIVER', 'database-uuids'),
        'database' => env('DB_CONNECTION', 'mysql'),
        'table' => 'failed_jobs',
    ],

    /*
    |--------------------------------------------------------------------------
    | Named queue lanes
    |--------------------------------------------------------------------------
    |
    | Logical queue names used across the app. AI generation jobs are routed
    | to one of ai-high / ai-default / ai-low based on the user's plan (see
    | App\Services\Queue\PlanQueueResolver); everything else uses a fixed,
    | purpose-specific lane so a burst of AI work never starves notifications
    | or scheduled-post publishing.
    |
    */
    'names' => [
        'ai_high' => env('QUEUE_AI_HIGH', 'ai-high'),
        'ai_default' => env('QUEUE_AI_DEFAULT', 'ai-default'),
        'ai_low' => env('QUEUE_AI_LOW', 'ai-low'),
        'notifications' => env('QUEUE_NOTIFICATIONS', 'notifications'),
        'default' => env('QUEUE_DEFAULT_LANE', 'default'),
    ],

    // Backward-compatible single key some older code still reads directly.
    'ai_queue' => env('QUEUE_AI_DEFAULT', 'ai-default'),

];
