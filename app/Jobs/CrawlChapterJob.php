<?php

namespace App\Jobs;

use App\Models\ScrapedChapter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CrawlChapterJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 90;

    public function __construct(public ScrapedChapter $chapter) {}

    public function handle(): void
    {
        $this->chapter->update(['status' => 'processing']);

        try {
            // Cào HTML của chương
            $scraperPath = '/var/www/read-app/spy-doctruyen/scraper.cjs';
            $html = shell_exec("cd /var/www/read-app/spy-doctruyen && node {$scraperPath} " . escapeshellarg($this->chapter->chapter_url));

            if (!$html || strlen(trim($html)) < 200) {
                throw new \Exception("HTML rỗng khi cào chương: {$this->chapter->chapter_url}");
            }

            libxml_use_internal_errors(true);
            $dom = new \DOMDocument();
            $dom->loadHTML($html);
            libxml_clear_errors();
            $xpath = new \DOMXPath($dom);

            // Lấy nội dung chương - truyencom.com dùng div.chapter-content
            $contentNodes = $xpath->query('//*[contains(@class,"chapter-content")]|//*[@id="chapter-content"]|//*[contains(@class,"novel-content")]');
            $content = '';
            if ($contentNodes->length > 0) {
                $content = $contentNodes->item(0)->textContent;
            }

            if (empty(trim($content))) {
                throw new \Exception("Không tìm thấy nội dung chương tại: {$this->chapter->chapter_url}");
            }

            // Xác định đường dẫn lưu file
            $story = $this->chapter->story;
            $slug  = $story->slug ?? basename(rtrim($story->url, '/'));
            $storyDir  = storage_path("app/stories/{$slug}");

            if (!is_dir($storyDir)) {
                mkdir($storyDir, 0755, true);
            }

            $chapterTitle = $this->chapter->chapter_title ?? "Chương " . $this->chapter->order_index;
            $contentPath  = "{$storyDir}/chapter_{$this->chapter->order_index}.txt";

            // Lưu file text của chương
            $fileContent = "=== {$chapterTitle} ===\n\n" . trim($content) . "\n\n";
            file_put_contents($contentPath, $fileContent);

            // Ghi nối vào file full truyện
            $fullPath = "{$storyDir}/full_story.txt";
            file_put_contents($fullPath, $fileContent, FILE_APPEND);

            $this->chapter->update([
                'status'       => 'completed',
                'content_path' => $contentPath,
            ]);

            Log::info("[CrawlChapterJob] Đã lưu: {$slug}/{$chapterTitle}");

        } catch (\Exception $e) {
            Log::error("[CrawlChapterJob] Lỗi chương {$this->chapter->chapter_url}: " . $e->getMessage());
            $this->chapter->update([
                'status'     => 'failed',
                'last_error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
