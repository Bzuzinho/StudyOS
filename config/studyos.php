<?php

return [
    'calendar' => [
        'inforestudante_url' => env('INFORESTUDANTE_ICAL_URL'),
        'allowed_hosts' => array_values(array_filter(array_map(
            'trim',
            explode(',', env('STUDYOS_CALENDAR_ALLOWED_HOSTS', 'inforestudante.ipleiria.pt,inforestudante.ulo.pt,ead.ulo.pt'))
        ))),
        'past_days' => (int) env('STUDYOS_CALENDAR_PAST_DAYS', 120),
        'future_days' => (int) env('STUDYOS_CALENDAR_FUTURE_DAYS', 420),
        'mark_missing_as_cancelled' => filter_var(
            env('STUDYOS_CALENDAR_MARK_MISSING_CANCELLED', false),
            FILTER_VALIDATE_BOOL
        ),
    ],

    'moodle' => [
        'base_url' => rtrim(env('MOODLE_BASE_URL', 'https://ead.ulo.pt/2026-27'), '/'),
        'token' => env('MOODLE_TOKEN'),
        'private_token' => env('MOODLE_PRIVATE_TOKEN'),
        'username' => env('MOODLE_USERNAME'),
        'password' => env('MOODLE_PASSWORD'),
        'enabled' => filter_var(env('MOODLE_ENABLED', true), FILTER_VALIDATE_BOOL),
        'max_file_bytes' => (int) env('MOODLE_MAX_FILE_BYTES', 26214400),
        'missing_threshold' => (int) env('MOODLE_MISSING_THRESHOLD', 2),
        'allowed_extensions' => array_values(array_filter(array_map(
            fn ($value) => strtolower(trim($value)),
            explode(',', env('MOODLE_ALLOWED_EXTENSIONS', 'pdf,pptx,docx,txt,md'))
        ))),
    ],
];
