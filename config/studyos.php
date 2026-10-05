<?php

return [
    'calendar' => [
        'allowed_hosts' => array_values(array_filter(array_map(
            'trim',
            explode(',', env('STUDYOS_CALENDAR_ALLOWED_HOSTS', 'inforestudante.ipleiria.pt,inforestudante.ulo.pt,ead.ulo.pt'))
        ))),
        'past_days' => (int) env('STUDYOS_CALENDAR_PAST_DAYS', 120),
        'future_days' => (int) env('STUDYOS_CALENDAR_FUTURE_DAYS', 420),
        'mark_missing_as_cancelled' => (bool) env('STUDYOS_CALENDAR_MARK_MISSING_CANCELLED', false),
    ],
];
