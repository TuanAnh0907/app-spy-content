<?php

namespace App\Jobs;

use App\Models\DtruyenStory;
use App\Models\ScrapedChapter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job xử lý 1 bộ truyện từ bảng dtruyen_stories (Thread 2).
 * Chỉ crawl trang 1 của danh sách chương, sau đó dispatch
 * CrawlChapterPageJob cho các trang còn lại để tránh timeout.
 */
class ProcessDtruyenStoryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;

    public function __construct(public DtruyenStory $story) {}

    public function handle(): void
    {
        $this->story->update(['status' => 'processing']);
        $scraperPath = '/var/www/read-app/spy-doctruyen/scraper.cjs';

        try {
            $html = shell_exec("cd /var/www/read-app/spy-doctruyen && node {$scraperPath} " . escapeshellarg($this->story->url));

            if (!$html || strlen(trim($html)) < 500) {
                throw new \Exception("HTML rỗng hoặc bị block: {$this->story->url}");
            }

            $dom   = $this->parseDom($html);
            $xpath = new \DOMXPath($dom);

            // --- Lấy thông tin truyện ---
            $titleNodes = $xpath->query('//h1|//h3');
            $title = $titleNodes->length > 0 ? trim($titleNodes->item(0)->textContent) : 'Chưa rõ';

            $authorNodes = $xpath->query('//*[contains(@class,"author")]//a|//a[@itemprop="author"]');
            $author = $authorNodes->length > 0 ? trim($authorNodes->item(0)->textContent) : 'Không rõ';

            $slug = basename(rtrim($this->story->url, '/'));
            $this->story->update(['title' => $title, 'author' => $author, 'slug' => $slug]);

            // Tạo thư mục lưu truyện
            $storyDir = storage_path("app/stories/{$slug}");
            if (!is_dir($storyDir)) {
                mkdir($storyDir, 0755, true);
            }

            // --- Xác định tổng số trang danh sách chương ---
            $pageLinks = $xpath->query('//a[contains(@href, "trang-") and contains(@href, "#chapter-list")]');
            $maxPage = 1;
            foreach ($pageLinks as $pl) {
                if (preg_match('/trang-(\d+)/', $pl->getAttribute('href'), $m)) {
                    $maxPage = max($maxPage, (int) $m[1]);
                }
            }

            // --- Lấy chương trang 1 ngay ---
            $orderIndex = $this->extractAndSaveChapters($xpath, $this->story->id, 0);

            // --- Dispatch Job từng trang còn lại ---
            for ($page = 2; $page <= $maxPage; $page++) {
                $pageUrl = rtrim($this->story->url, '/') . "/trang-{$page}/#chapter-list";
                CrawlChapterPageJob::dispatch($this->story, $pageUrl, ($page - 1) * 55)
                    ->onQueue('stories')
                    ->delay(now()->addSeconds(($page - 1) * 15));
            }

            // Nếu chỉ có 1 trang thì đánh dấu hoàn thành ngay
            if ($maxPage === 1) {
                $this->story->update(['status' => 'completed', 'total_chapters' => $orderIndex]);
            }

            Log::info("[ProcessDtruyenStoryJob] '{$title}' -> {$maxPage} trang chương, dispatch thêm " . ($maxPage - 1) . " Job trang.");

        } catch (\Exception $e) {
            Log::error("[ProcessDtruyenStoryJob] Lỗi: " . $e->getMessage());
            $this->story->update(['status' => 'failed', 'last_error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function extractAndSaveChapters(\DOMXPath $xpath, int $storyId, int $startIndex): int
    {
        $chapterLinks = $xpath->query('//*[@id="chapter-list"]//a');
        $index = $startIndex;

        foreach ($chapterLinks as $link) {
            $href = $link->getAttribute('href');
            if (!str_contains($href, 'chuong-')) continue;

            $chapterUrl   = str_starts_with($href, 'http') ? $href : 'https://truyencom.com' . $href;
            $chapterUrl   = rtrim($chapterUrl, '/');
            $chapterTitle = trim($link->textContent);

            $chapter = ScrapedChapter::firstOrCreate(
                ['chapter_url' => $chapterUrl],
                [
                    'story_id'      => $storyId,
                    'order_index'   => $index++,
                    'chapter_title' => $chapterTitle,
                    'status'        => 'pending',
                ]
            );

            if ($chapter->wasRecentlyCreated || $chapter->status !== 'completed') {
                CrawlChapterJob::dispatch($chapter)->onQueue('chapters');
            }
        }

        return $index;
    }

    private function parseDom(string $html): \DOMDocument
    {
        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTML($html);
        libxml_clear_errors();
        return $dom;
    }
}
