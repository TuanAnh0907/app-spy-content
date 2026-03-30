<?php

namespace App\Console\Commands\TruyenFull;

use App\Models\TruyenFull\Story;
use App\Jobs\TruyenFull\CrawlStoryChapterBatchJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CrawlOngoingStoriesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'truyenfull:continue-ongoing';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Resume crawling for ongoing TruyenFull stories that are scheduled for retry.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $stories = Story::query()
            ->where('is_ongoing', true)
            ->whereNotNull('next_crawl_at')
            ->where('next_crawl_at', '<=', now())
            ->get();

        if ($stories->isEmpty()) {
            $this->info("Không có truyện TruyenFull nào đang ra cần cào lại (hoặc chưa đến hạn).");
            return Command::SUCCESS;
        }

        foreach ($stories as $story) {
            $maxCompletedOrder = $story->chapters()
                ->where('status', 'completed')
                ->max('order_index') ?? 0;

            $nextOrderIndex = $maxCompletedOrder + 1;

            Log::channel('truyenfull')->info("[TruyenFull] Tiếp tục cào truyện đang ra: {$story->title} từ chương $nextOrderIndex. Lần thử: {$story->crawl_retry_count}");

            // Xóa next_crawl_at để không bị chạy cron liên tục khi batch đang xử lý
            $story->update(['next_crawl_at' => null]);

            CrawlStoryChapterBatchJob::dispatch($story, $nextOrderIndex)->onQueue('tf-chapters');
            $this->info("Đã dispatch batch từ chương $nextOrderIndex cho truyện: {$story->title}");
        }

        $this->info("Hoàn tất dispatch các truyện ongoing TruyenFull.");
        return Command::SUCCESS;
    }
}
