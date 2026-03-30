<?php

namespace App\Jobs\TruyenFull;

use App\Enums\StoryStatus;
use App\Models\TruyenFull\Chapter;
use App\Models\TruyenFull\Story;
use App\Services\StoryDeduplicationService;
use DOMDocument;
use DOMXPath;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Job xử lý 1 bộ truyện từ bảng tf_stories (Thread 2).
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

            // ── Dedup check (bậc 1): so normalized title + author với DTruyen ──
            $dedup  = new StoryDeduplicationService();
            $result = $dedup->checkDuplicate(
                $this->story->normalized_title ?? '',
                $this->story->normalized_author ?? '',
                'truyenfull'
            );

            if ($result['duplicate']) {
                $reason = "Duplicate of {$result['source']}#{$result['id']} ({$result['title']})";
                $this->story->update([
                    'status'         => StoryStatus::SKIPPED,
                    'skipped_reason' => $reason,
                ]);
                Log::channel('truyenfull')->info("[TruyenFull][ProcessStoryJob] Skip '{$this->story->title}' — $reason");
                return;
            }
            // ────────────────────────────────────────────────────────────────────

            $this->ensureStorageDirectoryExists($slug);

            // Download cover image
            $coverImage = $this->downloadCoverImage($xpath, $slug);
            if ($coverImage) {
                $this->story->update(['cover_image' => $coverImage]);
            }

            // Chỉ lưu thông tin chương vào DB (trang 1) rồi khởi chạy batch
            $this->extractAndSaveChaptersInDb($xpath, $this->story->id);
            
            CrawlStoryChapterBatchJob::dispatch($this->story)->onQueue('tf-chapters');

        } catch (Exception $e) {
            Log::channel('truyenfull')->error("[TruyenFull][ProcessStoryJob] Lỗi: ".$e->getMessage());
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
            $html = shell_exec("cd ".escapeshellarg(base_path())." && node ".escapeshellarg($scraperPath)." ".escapeshellarg($url));

            if ($html && strlen(trim($html)) >= 500) {
                return $html;
            }

            if ($i < $maxRetries) {
                Log::channel('truyenfull')->warning("[TruyenFull][fetchHtml] Lần thử ".($i + 1)." thất bại cho URL: $url. Đang thử lại sau 10s...");
                sleep(10);
            }
        }

        throw new Exception("HTML rỗng hoặc bị block sau $maxRetries lần thử: $url");
    }

    protected function extractAndSaveStoryInfo(DOMXPath $xpath): string
    {
        // TruyenFull dùng h3.title hoặc h1 cho tên truyện
        $titleNodes  = $xpath->query('//h3[contains(@class,"title")]|//h1');
        $authorNodes = $xpath->query('//*[contains(@class,"author")]//a|//a[@itemprop="author"]');

        $title  = $titleNodes->length > 0 ? trim($titleNodes->item(0)->textContent) : 'Chưa rõ';
        $author = $authorNodes->length > 0 ? trim($authorNodes->item(0)->textContent) : 'Không rõ';
        $slug   = basename(rtrim($this->story->url, '/'));

        // Parse status to determine if the story is ongoing
        $isOngoing = true;
        $infoNodes = $xpath->query('//*[contains(@class, "info")] | //*[contains(@class, "truyen-info")] | //*[contains(@class, "story-info")]');
        if ($infoNodes->length > 0) {
            $infoText = strtolower($infoNodes->item(0)->textContent ?? '');
            if (str_contains($infoText, 'hoàn thành') || str_contains($infoText, 'full') || str_contains($infoText, 'đã hoàn thành')) {
                $isOngoing = false;
            }
        }

        $dedup = new StoryDeduplicationService();

        // Download cover image
        $coverImagePath = $this->downloadCoverImage($xpath, $slug);

        $this->story->update([
            'title'             => $title,
            'author'            => $author,
            'slug'              => $slug,
            'cover_image'       => $coverImagePath,
            'normalized_title'  => $dedup->normalize($title),
            'normalized_author' => $dedup->normalize($author),
            'is_ongoing'        => $isOngoing,
            'crawl_retry_count' => 0,
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



    public function extractAndSaveChaptersInDb(DOMXPath $xpath, int $storyId): void
    {
        // TruyenFull: list chương nằm trong các thẻ <li> của .list-chapter
        $chapterLinks = $xpath->query('//ul[contains(@class,"list-chapter")]//li//a');

        Log::channel('truyenfull')->info("[TruyenFull][ProcessStoryJob] Debug Chapters: found ".$chapterLinks->length." links.");

        foreach ($chapterLinks as $link) {
            $href = (string) $link->getAttribute('href');

            // Bỏ qua link không phải chương (như link trang, link quảng cáo...)
            if (!str_contains($href, 'chuong') && !str_contains($href, 'chapter')) {
                continue;
            }

            $chapterUrl   = str_starts_with($href, 'http') ? $href : 'https://'.parse_url($this->story->url,
                    PHP_URL_HOST).$href;
            $chapterUrl   = rtrim($chapterUrl, '/');
            $chapterTitle = trim($link->textContent);

            $orderIndex = 0;
            if (preg_match('/chuong[- _](\d+)/i', $href, $m) || preg_match('/chapter[- _](\d+)/i', $href, $m)) {
                $orderIndex = (int) $m[1];
            } elseif (preg_match('/Chương (\d+)/i', $chapterTitle, $m)) {
                $orderIndex = (int) $m[1];
            }

            Chapter::query()
                ->firstOrCreate(
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
            // Tìm ảnh cover (truyenfull thường dùng class "book-cover", "thumbnail", "cover" hoặc trong div.book)
            $imgNodes = $xpath->query('
                //img[contains(@class, "book-cover")]/@src |
                //img[contains(@class, "cover")]/@src |
                //img[contains(@class, "thumbnail")]/@src |
                //*[contains(@class, "book")]//img/@src |
                //*[contains(@class, "info-holder")]//img/@src |
                //div[contains(@class, "books")]//img/@src
            ');

            if ($imgNodes->length === 0) {
                Log::channel('truyenfull')->warning("[TruyenFull][ProcessStoryJob] Không tìm thấy ảnh cover cho: {$this->story->url}");
                return null;
            }

            $imgUrl = trim($imgNodes->item(0)->nodeValue);

            // Chuyển thành URL đầy đủ nếu là relative URL
            if (!str_starts_with($imgUrl, 'http')) {
                $host   = parse_url($this->story->url, PHP_URL_HOST);
                $imgUrl = 'https://'.$host.$imgUrl;
            }

            // Download ảnh
            $response = Http::timeout(30)->get($imgUrl);

            if (!$response->successful()) {
                Log::channel('truyenfull')->warning("[TruyenFull][ProcessStoryJob] Không thể download ảnh: $imgUrl");
                return null;
            }

            // Lấy extension từ URL hoặc mặc định là jpg
            $extension = 'jpg';
            if (preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $imgUrl, $matches)) {
                $extension = strtolower($matches[1]);
            }

            // Lưu vào storage/app/covers/{slug}/cover.{ext}
            $coverPath = "covers/$slug/cover.$extension";
            $disk      = Storage::disk('local');

            $disk->put($coverPath, $response->body());

            Log::channel('truyenfull')->info("[TruyenFull][ProcessStoryJob] Đã download cover: $coverPath");

            return $coverPath;

        } catch (Exception $e) {
            Log::channel('truyenfull')->error("[TruyenFull][ProcessStoryJob] Lỗi download cover: ".$e->getMessage());
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
