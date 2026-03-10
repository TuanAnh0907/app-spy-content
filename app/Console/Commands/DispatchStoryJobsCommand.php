<?php

namespace App\Console\Commands;

use App\Jobs\ProcessDtruyenStoryJob;
use App\Models\DtruyenStory;
use Illuminate\Console\Command;

class DispatchStoryJobsCommand extends Command
{
    protected $signature = 'crawl:dispatch-stories {--limit=100 : Số truyện mỗi lần dispatch}';

    protected $description = 'Dispatch ProcessDtruyenStoryJob cho các truyện đang pending trong bảng dtruyen_stories (Thread 2)';

    public function handle(): void
    {
        $limit = (int) $this->option('limit');

        $stories = DtruyenStory::query()
            ->where('status', 'pending')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        if ($stories->isEmpty()) {
            $this->info('Không còn truyện nào pending để dispatch.');
            return;
        }

        $this->info("Đang dispatch {$stories->count()} truyện vào Queue (Thread 2)...");

        $bar = $this->output->createProgressBar($stories->count());
        $bar->start();

        foreach ($stories as $story) {
            $story->update(['status' => 'processing']);
            ProcessDtruyenStoryJob::dispatch($story)->onQueue('stories');
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('✅ Đã dispatch xong! Chạy "php artisan queue:work --queue=stories,chapters" để bắt đầu Thread 2.');
    }
}
