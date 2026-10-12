<?php

return [
    'enabled' => filter_var(env('STUDYOS_AI_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
    'api_key' => env('OPENAI_API_KEY'),
    'access_code' => env('STUDYOS_AI_ACCESS_CODE'),
    'model' => env('STUDYOS_AI_MODEL', 'gpt-5-mini'),
    'monthly_budget_usd' => (float) env('STUDYOS_AI_MONTHLY_BUDGET_USD', 10),
    'input_usd_per_million' => (float) env('STUDYOS_AI_INPUT_USD_PER_MILLION', 0.25),
    'output_usd_per_million' => (float) env('STUDYOS_AI_OUTPUT_USD_PER_MILLION', 2),
];
