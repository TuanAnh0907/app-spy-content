<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Spy Crawler Schedule
|--------------------------------------------------------------------------
|
| Crawl truyện mới hàng ngày từ các nguồn, sau đó tự động sync lên backend.
| Điều chỉnh frequency và source tuỳ nhu cầu.
|
*/

// Crawl nhẹ: mỗi 6 giờ, limit 5 truyện, sleep 20s/story
// Timeout 1800s (30 phút): 5 stories × worst-case ~300s (retries + sleep) = 1500s
Schedule::command('spy:run truyenfull --pages=1 --limit=5')
    ->everySixHours()
    ->withoutOverlapping(120)   // lock 2 giờ
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/spy-run-truyenfull.log'));

Schedule::command('spy:run tangthuvien --pages=1 --limit=5')
    ->everySixHours()
    ->withoutOverlapping(120)
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/spy-run-tangthuvien.log'));

Schedule::command('spy:run sstruyen --pages=1 --limit=5')
    ->everySixHours()
    ->withoutOverlapping(120)
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/spy-run-sstruyen.log'));

// Sync lên backend - nhẹ, timeout 2 phút là đủ
Schedule::command('spy:sync --all')
    ->hourly()
    ->withoutOverlapping(5)
    ->appendOutputTo(storage_path('logs/spy-sync.log'));
