<?php

namespace App\Jobs\TruyenFull;

use App\Models\TruyenFull\Chapter;
use App\Models\TruyenFull\Story;
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
        $this->story->update(['status' => 'processing']);

        try {
            $html = $this->fetchHtml($this->story->url);

            $dom   = $this->parseDom($html);
            $xpath = new DOMXPath($dom);

            $slug = $this->extractAndSaveStoryInfo($xpath);
            $this->ensureStorageDirectoryExists($slug);

            $maxPage = $this->determineMaxPage($xpath);

            $this->extractAndSaveChapters($xpath, $this->story->id);
            $this->dispatchSubsequentPages($maxPage);

            if ($maxPage === 1) {
                $totalCount = Chapter::query()->where('story_id', $this->story->id)->count();
                $this->story->update([
                    'status'         => 'completed',
                    'total_chapters' => $totalCount,
                    'last_error'     => null,
                ]);
            }

            Log::info("[TruyenFull][ProcessStoryJob] '{$this->story->title}' -> {$maxPage} trang chương.");

        } catch (Exception $e) {
            Log::error("[TruyenFull][ProcessStoryJob] Lỗi: " . $e->getMessage());
            $this->story->update(['status' => 'failed', 'last_error' => $e->getMessage()]);
            throw $e;
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

            if ($html && strlen(trim($html)) >= 500) {
                return $html;
            }

            if ($i < $maxRetries) {
                Log::warning("[TruyenFull][fetchHtml] Lần thử " . ($i+1) . " thất bại cho URL: {$url}. Đang thử lại sau 10s...");
                sleep(10);
            }
        }

        throw new Exception("HTML rỗng hoặc bị block sau {$maxRetries} lần thử: {$url}");
    }

    protected function extractAndSaveStoryInfo(DOMXPath $xpath): string
    {
        // TruyenFull dùng h3.title hoặc h1 cho tên truyện
        $titleNodes  = $xpath->query('//h3[contains(@class,"title")]|//h1');
        $authorNodes = $xpath->query('//*[contains(@class,"author")]//a|//a[@itemprop="author"]');

        $title  = $titleNodes->length > 0 ? trim($titleNodes->item(0)->textContent) : 'Chưa rõ';
        $author = $authorNodes->length > 0 ? trim($authorNodes->item(0)->textContent) : 'Không rõ';
        $slug   = basename(rtrim($this->story->url, '/'));

        $this->story->update([
            'title'  => $title,
            'author' => $author,
            'slug'   => $slug,
        ]);

        return $slug;
    }

    protected function ensureStorageDirectoryExists(string $slug): void
    {
        $disk = Storage::disk(config('filesystems.chapter_disk', 'chapters'));

        if (!$disk->exists($slug)) {
            $disk->makeDirectory($slug);
        }
    }

    protected function determineMaxPage(DOMXPath $xpath): int
    {
        // Phân trang có thể nằm trong ul.pagination hoặc là các link có chứa "trang-"
        $pageLinks = $xpath->query('//ul[contains(@class,"pagination")]//a|//a[contains(@href, "/trang-")]|//a[contains(@href, "page=")]');
        $maxPage   = 1;

        Log::info("[TruyenFull][ProcessStoryJob] Debug Pagination: found " . $pageLinks->length . " link nodes.");

        foreach ($pageLinks as $link) {
            $href = (string) $link->getAttribute('href');
            Log::debug("[TruyenFull][ProcessStoryJob] Pagination link detected: {$href}");
            if (preg_match('/page[=\-](\d+)/i', $href, $m) || preg_match('/trang-(\d+)/i', $href, $m)) {
                $maxPage = max($maxPage, (int) $m[1]);
            }
        }

        return $maxPage;
    }

    protected function dispatchSubsequentPages(int $maxPage): void
    {
        $isVision = str_contains($this->story->url, 'truyenfull.vision');

        for ($page = 2; $page <= $maxPage; $page++) {
            $baseUrl = rtrim($this->story->url, '/');
            $pageUrl = $isVision ? "{$baseUrl}/trang-{$page}/" : "{$baseUrl}/?page={$page}";

            CrawlChapterPageJob::dispatch($this->story, $pageUrl)
                ->onQueue('tf-stories')
                ->delay(now()->addSeconds(($page - 1) * 30));
        }
    }

    public function extractAndSaveChapters(DOMXPath $xpath, int $storyId): void
    {
        // TruyenFull: list chương nằm trong các thẻ <li> của .list-chapter
        $chapterLinks = $xpath->query('//ul[contains(@class,"list-chapter")]//li//a');

        Log::info("[TruyenFull][ProcessStoryJob] Debug Chapters: found " . $chapterLinks->length . " links.");

        foreach ($chapterLinks as $link) {
            $href = (string) $link->getAttribute('href');

            // Bỏ qua link không phải chương (như link trang, link quảng cáo...)
            if (!str_contains($href, 'chuong') && !str_contains($href, 'chapter')) {
                continue;
            }

            $parsedHref = parse_url($href);
            $chapterUrl = str_starts_with($href, 'http') ? $href : 'https://' . parse_url($this->story->url, PHP_URL_HOST) . $href;
            $chapterUrl = rtrim($chapterUrl, '/');
            $chapterTitle = trim($link->textContent);

            $orderIndex = 0;
            if (preg_match('/chuong[- _](\d+)/i', $href, $m) || preg_match('/chapter[- _](\d+)/i', $href, $m)) {
                $orderIndex = (int) $m[1];
            } elseif (preg_match('/Chương (\d+)/i', $chapterTitle, $m)) {
                $orderIndex = (int) $m[1];
            }

            $chapter = Chapter::firstOrCreate(
                ['chapter_url' => $chapterUrl],
                [
                    'story_id'      => $storyId,
                    'order_index'   => $orderIndex,
                    'chapter_title' => $chapterTitle,
                    'status'        => 'pending',
                ]
            );

            if ($chapter->wasRecentlyCreated || $chapter->status !== 'completed') {
                CrawlChapterJob::dispatch($chapter)->onQueue('tf-chapters');
            }
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
