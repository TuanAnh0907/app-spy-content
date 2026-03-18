<?php

namespace App\Jobs\DTruyen;

use App\Models\DTruyen\Chapter;
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
 * Job cào nội dung text 1 chương từ truyencom.com, lưu vào file txt.
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

            // Sleep 30-60s sau khi cào xong để tránh bị block IP
            sleep(rand(30, 60));

            Log::channel('dtruyen')->info("[DTruyen][CrawlChapterJob] Đã lưu chương: {$this->chapter->chapter_url}");

        } catch (Exception $e) {
            Log::channel('dtruyen')->error("[DTruyen][CrawlChapterJob] Lỗi chương {$this->chapter->chapter_url}: " . $e->getMessage());

            $this->chapter->update([
                'status'     => 'failed',
                'last_error' => $e->getMessage(),
            ]);

            // Bắt buộc sleep kể cả khi bị lỗi/chặn để hạ nhiệt, tránh block IP nặng hơn
            sleep(rand(60, 90));
        }
    }

    /**
     * @throws Exception
     */
    protected function fetchHtml(string $url): string
    {
        $scraperPath = base_path('scraper.cjs');
        $html        = shell_exec("cd " . escapeshellarg(base_path()) . " && node " . escapeshellarg($scraperPath) . " " . escapeshellarg($url));

        if (!$html || strlen(trim($html)) < 200) {
            throw new Exception("HTML rỗng hoặc Cloudflare block khi cào chương: {$url}");
        }

        return $html;
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

        $xpath   = new DOMXPath($dom);
        $queries = [
            '//*[@id="chapter-c"]',
            '//*[contains(@class,"chapter-c")]',
            '//*[contains(@class,"chapter-content")]',
            '//*[@id="chapter-content"]',
            '//*[contains(@class,"novel-content")]',
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
