<?php

namespace App\Jobs\DTruyen;

use App\Models\DTruyen\Story;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job cào 1 trang danh sách chương (trang 2, 3, 4...) của bộ truyện.
 * Được dispatch bởi ProcessStoryJob để xử lý các trang phân trang tiếp theo.
 */
class CrawlChapterPageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 120;

    public function __construct(public Story $story, public string $pageUrl)
    {
    }

    public function handle(): void
    {
        $scraperPath = base_path('scraper.cjs');
        $html        = shell_exec("cd " . escapeshellarg(base_path()) . " && node " . escapeshellarg($scraperPath) . " " . escapeshellarg($this->pageUrl));

        if (!$html || strlen(trim($html)) < 200) {
            Log::channel('dtruyen')->warning("[DTruyen][CrawlChapterPageJob] HTML rỗng: {$this->pageUrl}");
            return;
        }

        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTML($html);
        libxml_clear_errors();
        $xpath = new \DOMXPath($dom);

        (new ProcessStoryJob($this->story))->extractAndSaveChapters($xpath, $this->story->id);

        Log::channel('dtruyen')->info("[DTruyen][CrawlChapterPageJob] Đã crawl trang chương: {$this->pageUrl}");
    }
}
