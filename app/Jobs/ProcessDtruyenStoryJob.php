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
use Illuminate\Support\Facades\Storage;
use Exception;
use DOMDocument;
use DOMXPath;

/**
 * Job xử lý 1 bộ truyện từ bảng dtruyen_stories (Thread 2).
 * Chỉ crawl trang 1 của danh sách chương, sau đó dispatch
 * CrawlChapterPageJob cho các trang còn lại để tránh timeout.
 */
class ProcessDtruyenStoryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 120;

    public function __construct(public DtruyenStory $story)
    {
    }

    /**
     * @throws Exception
     */
    public function handle(): void
    {
        $this->story->update(['status' => 'processing']);

        try {
            $html = $this->fetchStoryHtml($this->story->url);

            $dom   = $this->parseDom($html);
            $xpath = new DOMXPath($dom);

            $slug = $this->extractAndSaveStoryInfo($xpath);
            $this->ensureStorageDirectoryExists($slug);

            $maxPage = $this->determineMaxPage($xpath);

            $this->extractAndSaveChapters($xpath, $this->story->id);
            $this->dispatchSubsequentPages($maxPage);

            if ($maxPage === 1) {
                // Ta có thể đếm số lượng chapters trong DB, hoặc mạo muội completed luôn
                $totalCount = ScrapedChapter::query()
                    ->where('story_id', $this->story->id)
                    ->count();

                $this->story->update(['status' => 'completed', 'total_chapters' => $totalCount]);
            }

            Log::info("[ProcessDtruyenStoryJob] '{$this->story->title}' -> {$maxPage} trang chương, dispatch thêm ".($maxPage - 1)." Job trang.");

        } catch (Exception $e) {
            Log::error("[ProcessDtruyenStoryJob] Lỗi: ".$e->getMessage());
            $this->story->update(['status' => 'failed', 'last_error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * @throws Exception
     */
    protected function fetchStoryHtml(string $url): string
    {
        $scraperPath = base_path('scraper.cjs');
        $html        = shell_exec("cd ".escapeshellarg(base_path())." && node ".escapeshellarg($scraperPath)." ".escapeshellarg($url));

        if (!$html || strlen(trim($html)) < 500) {
            throw new Exception("HTML rỗng hoặc bị block: {$url}");
        }

        return $html;
    }

    protected function extractAndSaveStoryInfo(DOMXPath $xpath): string
    {
        $titleNodes  = $xpath->query('//h1|//h3');
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
        $pageLinks = $xpath->query('//a[contains(@href, "trang-") and contains(@href, "#chapter-list")]');
        $maxPage   = 1;

        foreach ($pageLinks as $pl) {
            if (preg_match('/trang-(\d+)/', $pl->getAttribute('href'), $m)) {
                $maxPage = max($maxPage, (int) $m[1]);
            }
        }

        return $maxPage;
    }

    protected function dispatchSubsequentPages(int $maxPage): void
    {
        for ($page = 2; $page <= $maxPage; $page++) {
            $pageUrl = rtrim($this->story->url, '/')."/trang-{$page}/#chapter-list";
            CrawlChapterPageJob::dispatch($this->story, $pageUrl)
                ->onQueue('stories')
                ->delay(now()->addSeconds(($page - 1) * 30)); // 30s giữa mỗi trang chapter
        }
    }

    public function extractAndSaveChapters(DOMXPath $xpath, int $storyId): void
    {
        $chapterLinks = $xpath->query('//*[contains(@class, "list-chapter")]//a|//*[contains(@class, "l-chapter")]//a|//*[@id="chapter-list"]//a');

        foreach ($chapterLinks as $link) {
            $href = $link->getAttribute('href');
            if (!str_contains($href, 'chuong-')) {
                continue;
            }

            $chapterUrl   = str_starts_with($href, 'http') ? $href : 'https://truyencom.com'.$href;
            $chapterUrl   = rtrim($chapterUrl, '/');
            $chapterTitle = trim($link->textContent);

            // Bóc tách số thứ tự chương từ URL (ví dụ: chuong-3752)
            $orderIndex = 0;
            if (preg_match('/chuong-(\d+)/i', $href, $m)) {
                $orderIndex = (int) $m[1];
            } elseif (preg_match('/Chương (\d+)/i', $chapterTitle, $m)) {
                $orderIndex = (int) $m[1];
            }

            $chapter = ScrapedChapter::firstOrCreate(
                ['chapter_url' => $chapterUrl],
                [
                    'story_id'      => $storyId,
                    'order_index'   => $orderIndex,
                    'chapter_title' => $chapterTitle,
                    'status'        => 'pending',
                ]
            );

            if ($chapter->wasRecentlyCreated || $chapter->status !== 'completed') {
                CrawlChapterJob::dispatch($chapter)->onQueue('chapters');
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
