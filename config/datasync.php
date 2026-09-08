<?php

return [
    'target_url' => env(
        'DATASYNC_TARGET_URL',
        'https://w68autoparts.hostforgeplatforms.com'
    ),

    'token' => env('DATASYNC_TOKEN', ''),

    'source_enabled' => filter_var(
        env(
            'DATASYNC_SOURCE_ENABLED',
            env('APP_ENV', 'production') === 'local'
        ),
        FILTER_VALIDATE_BOOL
    ),

    'target_enabled' => filter_var(
        env(
            'DATASYNC_TARGET_ENABLED',
            env('APP_ENV', 'production') === 'production'
        ),
        FILTER_VALIDATE_BOOL
    ),

    'verify_ssl' => filter_var(
        env('DATASYNC_VERIFY_SSL', true),
        FILTER_VALIDATE_BOOL
    ),

    // V4: stay comfortably below common nginx request-body limits.
    // DataSyncService will also halve a chunk automatically on HTTP 413.
    'chunk_size' => (int) env('DATASYNC_CHUNK_SIZE', 250),
    'max_payload_bytes' => (int) env(
        'DATASYNC_MAX_PAYLOAD_BYTES',
        524288
    ),

    'auto_interval_seconds' => (int) env(
        'DATASYNC_AUTO_INTERVAL',
        1800
    ),
    'connect_timeout' => (int) env(
        'DATASYNC_CONNECT_TIMEOUT',
        15
    ),
    'request_timeout' => (int) env(
        'DATASYNC_REQUEST_TIMEOUT',
        180
    ),

    'connections' => [
        'mysql' => [
            'label' => 'System',
            'icon' => 'shield',
        ],
        'masterlist' => [
            'label' => 'Master List',
            'icon' => 'package-search',
        ],
        'purchase' => [
            'label' => 'Purchase',
            'icon' => 'shopping-cart',
        ],
        'sales' => [
            'label' => 'Sales',
            'icon' => 'banknote',
        ],
        'ledger' => [
            'label' => 'Ledger',
            'icon' => 'layers',
        ],
        'accounting' => [
            'label' => 'Accounting',
            'icon' => 'calculator',
        ],
    ],

    'excluded_tables' => [
        'cache',
        'cache_locks',
        'sessions',
        'password_reset_tokens',
        'migrations',
        'jobs',
        'job_batches',
        'failed_jobs',
        'telescope_entries',
        'telescope_entries_tags',
        'telescope_monitoring',
        'personal_access_tokens',
    ],

    'excluded_prefixes' => [
        '_tmp_',
        'tmp_',
    ],
];
