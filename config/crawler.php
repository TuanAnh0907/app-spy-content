<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Crawler Settings
    |--------------------------------------------------------------------------
    |
    | Các cấu hình về tốc độ crawl, timeout, retry, và các thông số khác.
    |
    */

    'delay_ms' => (int) env('CRAWLER_DELAY_MS', 1500),
    'timeout'  => (int) env('CRAWLER_TIMEOUT', 30),
    'retries'  => (int) env('CRAWLER_RETRIES', 3),

    'delays' => [
        'story_s'   => (int) env('CRAWLER_STORY_DELAY_S', 30),
        'chapter_s' => (int) env('CRAWLER_CHAPTER_DELAY_S', 5),
    ],

    'stale_minutes' => (int) env('CRAWLER_STALE_MINUTES', 60),

    'user_agent' => env('CRAWLER_USER_AGENT',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36'),
];
