<?php

namespace App\Console\Commands\DTruyen;

use App\Enums\StoryStatus;
use App\Jobs\DTruyen\ProcessStoryJob;
use App\Models\DTruyen\Story;
use Illuminate\Console\Command;

/**
 * Command Thread 2: Dispatch ProcessStoryJob cho các truyện pending trong dtruyen_stories.
 */
class DispatchStoriesCommand extends Command
{
    protected $signature   = 'crawl:dispatch-stories {--limit=100 : Số truyện mỗi lần dispatch}';
    protected $description = 'Thread 2 - Dispatch job xử lý từng truyện pending trong dtruyen_stories';

    public function handle(): void
    {
        $limit = (int) $this->option('limit');

        $stories = Story::query()
            ->where('status', StoryStatus::PENDING)
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
            $story->update(['status' => StoryStatus::PROCESSING]);
            ProcessStoryJob::dispatch($story)->onQueue('stories');
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('✅ Dispatch xong! Chạy "php artisan queue:work --queue=stories,chapters" để bắt đầu Thread 2.');
    }
}
