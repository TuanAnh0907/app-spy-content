<?php

namespace App\Jobs\DTruyen;

use App\Models\DTruyen\Chapter;
use App\Models\DTruyen\Story;
use App\Enums\StoryStatus;
use App\Services\StoryDeduplicationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Exception;
use DOMDocument;
use DOMXPath;

/**
 * Job xử lý 1 bộ truyện từ bảng dtruyen_stories (Thread 2).
 * Cào trang 1 danh sách chương, lấy metadata, tạo thư mục lưu trữ,
 * sau đó dispatch CrawlChapterPageJob cho các trang phân trang còn lại.
 */
class ProcessStoryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 120;

    public function __construct(public Story $story)
    {
    }

    /**
     * @throws Exception
     */
    public function handle(): void
    {
        $this->story->update(['status' => StoryStatus::PROCESSING]);

        try {
            $html = $this->fetchHtml($this->story->url);

            $dom   = $this->parseDom($html);
            $xpath = new DOMXPath($dom);

            $slug = $this->extractAndSaveStoryInfo($xpath);

            // ── Dedup check (bậc 1): so normalized title + author với TruyenFull ──
            $dedup  = new StoryDeduplicationService();
            $result = $dedup->checkDuplicate(
                $this->story->normalized_title ?? '',
                $this->story->normalized_author ?? '',
                'dtruyen'
            );

            if ($result['duplicate']) {
                $reason = "Duplicate of {$result['source']}#{$result['id']} ({$result['title']})";
                $this->story->update([
                    'status'         => StoryStatus::SKIPPED,
                    'skipped_reason' => $reason,
                ]);
                Log::channel('dtruyen')->info("[DTruyen][ProcessStoryJob] Skip '{$this->story->title}' — $reason");
                return;
            }
            // ────────────────────────────────────────────────────────────────────

            $this->ensureStorageDirectoryExists($slug);

            // Download cover image
            $coverImage = $this->downloadCoverImage($xpath, $slug);
            if ($coverImage) {
                $this->story->update(['cover_image' => $coverImage]);
            }

            $this->determineMaxPage($xpath);

            // Chỉ lưu thông tin chương vào DB, không dispatch lẻ CrawlChapterJob ở đây nữa
            $this->extractAndSaveChaptersInDb($xpath, $this->story->id);
            $this->dispatchSubsequentPages();

        } catch (Exception $e) {
            Log::channel('dtruyen')->error("[DTruyen][ProcessStoryJob] Lỗi: " . $e->getMessage());
            $this->story->update(['status' => StoryStatus::FAILED, 'last_error' => $e->getMessage()]);
        }
    }

    /**
     * @throws Exception
     */
    protected function fetchHtml(string $url): string
    {
        $scraperPath = base_path('scraper.cjs');
        $maxRetries  = 2;

        for ($i = 0; $i <= $maxRetries; $i++) {
            // Đảm bảo scraper chạy với host truyencom.com cho DTruyen
            $html = shell_exec("cd " . escapeshellarg(base_path()) . " && node " . escapeshellarg($scraperPath) . " " . escapeshellarg($url));

            if ($html && strlen(trim($html)) >= 500) {
                return $html;
            }

            if ($i < $maxRetries) {
                Log::channel('dtruyen')->warning("[DTruyen][fetchHtml] Lần thử " . ($i+1) . " thất bại cho URL: $url. Đang thử lại sau 10s...");
                sleep(10);
            }
        }

        throw new Exception("HTML rỗng hoặc bị block sau $maxRetries lần thử: $url");
    }

    protected function extractAndSaveStoryInfo(DOMXPath $xpath): string
    {
        $titleNodes  = $xpath->query('//h1|//h3');
        $authorNodes = $xpath->query('//*[contains(@class,"author")]//a|//a[@itemprop="author"]');

        $title  = $titleNodes->length > 0 ? trim($titleNodes->item(0)->textContent) : 'Chưa rõ';
        $author = $authorNodes->length > 0 ? trim($authorNodes->item(0)->textContent) : 'Không rõ';
        $slug   = basename(rtrim($this->story->url, '/'));

        // Parse status to determine if the story is ongoing
        $isOngoing = true;
        $infoNodes = $xpath->query('//*[contains(@class, "info")] | //*[contains(@class, "truyen-info")] | //*[contains(@class, "story-info")]');
        if ($infoNodes->length > 0) {
            $infoText = strtolower($infoNodes->item(0)->textContent ?? '');
            if (str_contains($infoText, 'hoàn thành') || str_contains($infoText, 'full')) {
                $isOngoing = false;
            }
        }

        $dedup = new StoryDeduplicationService();

        $this->story->update([
            'title'             => $title,
            'author'            => $author,
            'slug'              => $slug,
            'normalized_title'  => $dedup->normalize($title),
            'normalized_author' => $dedup->normalize($author),
            'is_ongoing'        => $isOngoing,
            'crawl_retry_count' => 0, // Reset when first processed
        ]);

        return $slug;
    }

    protected function ensureStorageDirectoryExists(string $slug): void
    {
        $disk = Storage::disk(config('filesystems.chapter_disk'));

        if (!$disk->exists($slug)) {
            $disk->makeDirectory($slug);
        }
    }

    protected function determineMaxPage(DOMXPath $xpath): int
    {
        $pageLinks = $xpath->query('//a[contains(@href, "trang-") and contains(@href, "#chapter-list")]');
        $maxPage   = 1;

        foreach ($pageLinks as $pl) {
            if (preg_match('/trang-(\d+)/', $pl->getAttribute('href'), $m)) {
                $maxPage = max($maxPage, (int) $m[1]);
            }
        }

        return $maxPage;
    }

    /**
     * @return void
     */
    protected function dispatchSubsequentPages(): void
    {
        // Thay vì dispatch các trang chương, ta chuyển sang dùng Batching từ chương 1
        CrawlStoryChapterBatchJob::dispatch($this->story)->onQueue('chapters');
    }

    public function extractAndSaveChaptersInDb(DOMXPath $xpath, int $storyId): void
    {
        $chapterLinks = $xpath->query('//*[contains(@class, "list-chapter")]//a|//*[contains(@class, "l-chapter")]//a|//*[@id="chapter-list"]//a');

        foreach ($chapterLinks as $link) {
            $href = $link->getAttribute('href');
            if (!str_contains($href, 'chuong-')) {
                continue;
            }

            $chapterUrl   = str_starts_with($href, 'http') ? $href : 'https://truyencom.com' . $href;
            $chapterUrl   = rtrim($chapterUrl, '/');
            $chapterTitle = trim($link->textContent);

            // Bóc tách số thứ tự chương từ URL (ví dụ: chuong-3752)
            $orderIndex = 0;
            if (preg_match('/chuong-(\d+)/i', $href, $m)) {
                $orderIndex = (int) $m[1];
            } elseif (preg_match('/Chương (\d+)/i', $chapterTitle, $m)) {
                $orderIndex = (int) $m[1];
            }

            Chapter::firstOrCreate(
                ['chapter_url' => $chapterUrl],
                [
                    'story_id'      => $storyId,
                    'order_index'   => $orderIndex,
                    'chapter_title' => $chapterTitle,
                    'status'        => 'pending',
                ]
            );
        }
    }

    /**
     * Download cover image from story page
     */
    protected function downloadCoverImage(DOMXPath $xpath, string $slug): ?string
    {
        try {
            // Tìm ảnh cover (ưu tiên meta og:image, sau đó đến các container phổ biến)
            $imgNodes = $xpath->query('
                //*[@id="truyen"]/div[1]/div[1]/div[2]/div[1]/div/img/@src |
                //img[@itemprop="image"]/@src |
                //meta[@property="og:image"]/@content |
                //*[@id="book-img"]//img/@src |
                //div[contains(@class, "book-img")]//img/@src |
                //div[contains(@class, "book_avatar")]//img/@src |
                //img[contains(@class, "book-cover")]/@src |
                //img[contains(@class, "cover")]/@src |
                //img[contains(@class, "thumbnail")]/@src |
                //div[contains(@class, "lazyimg")]/@data-desk-image |
                //div[contains(@class, "lazyimg")]/@data-image
            ');

            if ($imgNodes->length === 0) {
                Log::channel('dtruyen')->warning("[DTruyen][ProcessStoryJob] Không tìm thấy ảnh cover cho: {$this->story->url}");
                return null;
            }

            $imgUrl = trim($imgNodes->item(0)->nodeValue);

            // Chuyển thành URL đầy đủ nếu là relative URL
            if (!str_starts_with($imgUrl, 'http')) {
                // DTruyen thường dùng cdn.truyencom.com cho ảnh
                $imgUrl = 'https://cdn.truyencom.com' . $imgUrl;
            }

            // Download ảnh
            $response = Http::timeout(30)->get($imgUrl);

            if (!$response->successful()) {
                Log::channel('dtruyen')->warning("[DTruyen][ProcessStoryJob] Không thể download ảnh: $imgUrl");
                return null;
            }

            // Lấy extension từ URL hoặc mặc định là jpg
            $extension = 'jpg';
            if (preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $imgUrl, $matches)) {
                $extension = strtolower($matches[1]);
            }

            // Lưu vào disk được cấu hình (local hoặc s3)
            $coverPath = "$slug/cover.$extension";
            $disk = Storage::disk(config('filesystems.cover_disk'));

            $disk->put($coverPath, $response->body());

            Log::channel('dtruyen')->info("[DTruyen][ProcessStoryJob] Đã download cover: $coverPath");

            return $coverPath;

        } catch (Exception $e) {
            Log::channel('dtruyen')->error("[DTruyen][ProcessStoryJob] Lỗi download cover: " . $e->getMessage());
            return null;
        }
    }

    private function parseDom(string $html): DOMDocument
    {
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML($html);
        libxml_clear_errors();

        return $dom;
    }
}
