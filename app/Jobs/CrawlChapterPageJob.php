<?php

namespace App\Jobs;

use App\Models\DtruyenStory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job cào 1 trang danh sách chương (trang 2, 3, 4...) của bộ truyện.
 * Được dispatch bởi ProcessDtruyenStoryJob.
 */
class CrawlChapterPageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;

    public function __construct(
        public DtruyenStory $story,
        public string $pageUrl,
        public int $startIndex,
    ) {}

    public function handle(): void
    {
        $scraperPath = '/var/www/read-app/spy-doctruyen/scraper.cjs';
        $storyJob    = new ProcessDtruyenStoryJob($this->story);

        $html = shell_exec("cd /var/www/read-app/spy-doctruyen && node {$scraperPath} " . escapeshellarg($this->pageUrl));

        if (!$html || strlen(trim($html)) < 200) {
            Log::warning("[CrawlChapterPageJob] HTML rỗng: {$this->pageUrl}");
            return;
        }

        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTML($html);
        libxml_clear_errors();
        $xpath = new \DOMXPath($dom);

        $storyJob->extractAndSaveChapters($xpath, $this->story->id, $this->startIndex);

        Log::info("[CrawlChapterPageJob] Đã crawl trang chương: {$this->pageUrl}");
    }
}
