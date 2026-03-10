<?php

namespace App\Console\Commands\TruyenFull;

use App\Enums\StoryStatus;
use App\Jobs\TruyenFull\ProcessStoryJob;
use App\Models\TruyenFull\Story;
use Illuminate\Console\Command;

/**
 * Command Thread 2: Dispatch ProcessStoryJob cho các truyện pending trong tf_stories.
 */
class DispatchStoriesCommand extends Command
{
    protected $signature   = 'crawl:tf-dispatch-stories {--limit=100 : Số truyện mỗi lần dispatch}';
    protected $description = 'Thread 2 - Dispatch job xử lý từng truyện pending trong tf_stories (TruyenFull)';

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

        $this->info("Đang dispatch {$stories->count()} truyện TruyenFull vào Queue...");

        $bar = $this->output->createProgressBar($stories->count());
        $bar->start();

        foreach ($stories as $story) {
            $story->update(['status' => StoryStatus::PROCESSING]);
            ProcessStoryJob::dispatch($story)->onQueue('tf-stories');
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('✅ Dispatch xong! Worker đang xử lý queue tf-stories và tf-chapters.');
    }
}
