<?php

namespace App\Jobs\TruyenFull;

use App\Models\TruyenFull\Chapter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Exception;
use DOMDocument;
use DOMXPath;

/**
 * Job cào nội dung text 1 chương từ TruyenFull, lưu vào file txt.
 * Được dispatch bởi ProcessStoryJob hoặc tái xử lý khi thất bại.
 */
class CrawlChapterJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 90;

    public function __construct(public Chapter $chapter)
    {
    }

    /**
     * @throws Exception
     */
    public function handle(): void
    {
        $this->chapter->update(['status' => 'processing']);

        try {
            $html    = $this->fetchHtml($this->chapter->chapter_url);
            $content = $this->extractChapterContent($html);
            $this->saveChapterContent($content);

            $this->chapter->update([
                'status'     => 'completed',
                'last_error' => null,
            ]);

            // Reset retry count on success
            if ($this->chapter->story->crawl_retry_count > 0) {
                $this->chapter->story->update(['crawl_retry_count' => 0]);
            }

            // Sleep 30-60s để tránh bị block IP
            sleep(rand(30, 60));

            Log::channel('truyenfull')->info("[TruyenFull][CrawlChapterJob] Đã lưu chương: {$this->chapter->chapter_url}");

        } catch (Exception $e) {
            Log::channel('truyenfull')->error("[TruyenFull][CrawlChapterJob] Lỗi chương {$this->chapter->chapter_url}: " . $e->getMessage());

            $this->chapter->update([
                'status'     => 'failed',
                'last_error' => $e->getMessage(),
            ]);

            // Sleep dài hơn khi lỗi để hạ nhiệt tránh bị block thêm
            sleep(rand(60, 90));

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        $story = $this->chapter->story;
        if (!$story || !$story->is_ongoing) {
            return;
        }

        if (str_contains($exception->getMessage(), 'Không tìm thấy nội dung')) {
            $story->increment('crawl_retry_count');
            if ($story->crawl_retry_count >= 4) {
                $story->update([
                    'is_ongoing' => false,
                ]);
                Log::channel('truyenfull')->info("[TruyenFull] Truyện {$story->title} quá 4 tuần không có chương mới. Ngừng theo dõi.");
            } else {
                $story->update([
                    'next_crawl_at' => now()->addDays(7),
                ]);
                Log::channel('truyenfull')->info("[TruyenFull] Truyện {$story->title} chưa có chương mới. Thử lại sau 7 ngày.");
            }
        } else {
            // Lỗi mạng hoặc block Cloudflare, thử lại nhanh hơn
            $story->update([
                'next_crawl_at' => now()->addHours(1),
            ]);
        }
    }

    /**
     * @throws Exception
     */
    protected function fetchHtml(string $url): string
    {
        $scraperPath = base_path('scraper.cjs');
        $html        = '';
        $maxRetries  = 2;

        for ($i = 0; $i <= $maxRetries; $i++) {
            $html = shell_exec("cd " . escapeshellarg(base_path()) . " && node " . escapeshellarg($scraperPath) . " " . escapeshellarg($url));

            if ($html && strlen(trim($html)) >= 200) {
                return $html;
            }

            if ($i < $maxRetries) {
                Log::channel('truyenfull')->warning("[TruyenFull][CrawlChapterJob] Lần thử " . ($i+1) . " thất bại cho URL: {$url}. Đang thử lại sau 10s...");
                sleep(10);
            }
        }

        throw new Exception("HTML rỗng hoặc Cloudflare block sau {$maxRetries} lần thử: {$url}");
    }

    /**
     * @throws Exception
     */
    protected function extractChapterContent(string $html): string
    {
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML($html);
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);

        // TruyenFull để nội dung chương trong #chapter-c
        $queries = [
            '//*[@id="chapter-c"]',
            '//*[contains(@class,"chapter-c")]',
            '//*[contains(@class,"chapter-content")]',
            '//*[@id="chapter-content"]',
        ];

        $contentNodes = $xpath->query(implode('|', $queries));
        $content      = $contentNodes->length > 0 ? trim($contentNodes->item(0)->textContent) : '';

        if (empty($content)) {
            throw new Exception("Không tìm thấy nội dung chương tại: {$this->chapter->chapter_url}");
        }

        return $content;
    }

    protected function saveChapterContent(string $content): void
    {
        $story = $this->chapter->story;
        $slug  = $story->slug ?? basename(rtrim($story->url, '/'));

        $disk = Storage::disk(config('filesystems.chapter_disk'));

        if (!$disk->exists($slug)) {
            $disk->makeDirectory($slug);
        }

        $chapterTitle = $this->chapter->chapter_title ?? "Chương " . $this->chapter->order_index;
        $relativePath = "{$slug}/chapter_{$this->chapter->order_index}.txt";
        $fullRelPath  = "{$slug}/full_story.txt";

        $fileContent = "=== {$chapterTitle} ===\n\n" . trim($content) . "\n";

        $disk->put($relativePath, $fileContent);
        $disk->append($fullRelPath, $fileContent);

        $this->chapter->update([
            'content_path' => '/' . ltrim($relativePath, '/'),
        ]);
    }
}
