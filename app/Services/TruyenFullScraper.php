<?php

namespace App\Services;

use App\Models\ScrapedChapter;
use App\Models\ScrapedStory;
use Symfony\Component\DomCrawler\Crawler;

class TruyenFullScraper extends BaseScraperService
{
    protected string $source  = 'truyenfull';
    protected string $baseUrl = 'https://truyenfull.io';

    /**
     * Crawl thông tin truyện + danh sách chương từ URL truyện
     * VD: https://truyenfull.io/ten-truyen/
     */
    public function scrapeStory(string $url): ?array
    {
        $crawler = $this->fetch($url, 'story');
        if (!$crawler) {
            return null;
        }

        try {
            $title       = $crawler->filter('.book h3.title')->first()->text('');
            $author      = $crawler->filter('.book .info div a[itemprop="author"]')->first()->text('') ?: null;
            $description = $crawler->filter('.desc-text')->count()
                ? $crawler->filter('.desc-text')->first()->text('')
                : null;

            // Lấy thể loại
            $genres = [];
            $crawler->filter('.info div a[itemprop="genre"]')->each(function ($node) use (&$genres) {
                $genres[] = trim($node->text());
            });

            // Status
            $statusText = $crawler->filter('.info .text-primary')->count()
                ? $crawler->filter('.info .text-primary')->first()->text('')
                : 'Đang ra';
            $status     = str_contains(mb_strtolower($statusText), 'hoàn') ? 'completed' : 'ongoing';

            // Cover URL (lưu để xử lý sau)
            $coverUrl = $crawler->filter('.book img')->count()
                ? $crawler->filter('.book img')->first()->attr('src')
                : null;

            // Slug từ URL (dùng làm source_id)
            $sourceId = rtrim(parse_url($url, PHP_URL_PATH), '/');
            $sourceId = basename($sourceId);

            // Tổng số chương (lấy từ số chương cuối cùng trong phân trang)
            $totalChapters = $this->getTotalChapters($crawler, $url);

            // Danh sách URL chương (page 1)
            $chapterUrls = $this->parseChapterList($crawler, $url);

            return [
                'source'         => $this->source,
                'source_id'      => $sourceId,
                'source_url'     => $url,
                'title'          => trim($title),
                'author'         => $author ? trim($author) : null,
                'description'    => $description ? trim($description) : null,
                'cover_url'      => $coverUrl,
                'status'         => $status,
                'genres'         => $genres,
                'total_chapters' => $totalChapters,
                'chapter_urls'   => $chapterUrls, // dùng nội bộ để queue crawl tiếp
            ];

        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error("[{$this->source}] Parse story error: {$url} — ".$e->getMessage());
            return null;
        }
    }

    /**
     * Crawl nội dung 1 chương
     * VD: https://truyenfull.io/ten-truyen/chuong-1/
     */
    public function scrapeChapter(string $url): ?array
    {
        $crawler = $this->fetch($url, 'chapter');
        if (!$crawler) {
            return null;
        }

        try {
            $title = $crawler->filter('.chapter-title')->count()
                ? $crawler->filter('.chapter-title')->first()->text('')
                : null;

            // Số chương từ URL: /ten-truyen/chuong-123/ → 123
            preg_match('/chuong-(\d+(?:-\d+)?)/i', $url, $m);
            $chapterNumber = isset($m[1]) ? (int) explode('-', $m[1])[0] : 0;

            // Nội dung chương
            $content = '';
            if ($crawler->filter('#chapter-c')->count()) {
                $content = $crawler->filter('#chapter-c')->html();
            } elseif ($crawler->filter('.chapter-c')->count()) {
                $content = $crawler->filter('.chapter-c')->html();
            }

            // Strip quảng cáo / script trong content
            $content   = $this->cleanContent($content);
            $wordCount = str_word_count(strip_tags($content));

            return [
                'source_url'     => $url,
                'chapter_number' => $chapterNumber,
                'title'          => $title ? trim($title) : null,
                'content'        => $content,
                'word_count'     => $wordCount,
            ];

        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error("[{$this->source}] Parse chapter error: {$url} — ".$e->getMessage());
            return null;
        }
    }

    /**
     * Lấy danh sách URL truyện từ trang /danh-sach/truyen-moi/?page=N
     */
    public function getStoryListUrls(int $page = 1): array
    {
        $url     = "{$this->baseUrl}/danh-sach/truyen-moi/trang-{$page}/";
        $crawler = $this->fetch($url, 'story');
        if (!$crawler) {
            return [];
        }

        $urls = [];
        $crawler->filter('.list-truyen .row h3.truyen-title a')->each(function ($node) use (&$urls) {
            $href = $node->attr('href');
            if ($href) {
                $urls[] = $href;
            }
        });

        return $urls;
    }

    // ─── Private helpers ─────────────────────────────────────────────────────

    private function getTotalChapters(Crawler $crawler, string $storyUrl): int
    {
        // Thử đếm từ phân trang: lấy trang cuối, rồi đếm chương
        $lastPage = 1;
        $crawler->filter('#list-chapter .pagination li a')->each(function ($node) use (&$lastPage) {
            $href = $node->attr('href');
            if ($href && preg_match('/trang-(\d+)/', $href, $m)) {
                $lastPage = max($lastPage, (int) $m[1]);
            }
        });

        if ($lastPage <= 1) {
            // Chỉ có 1 trang: đếm trực tiếp
            return $crawler->filter('#list-chapter ul.list-chapter li')->count();
        }

        // Lấy trang cuối để đếm chương cuối cùng
        $slug        = rtrim(parse_url($storyUrl, PHP_URL_PATH), '/');
        $lastPageUrl = "{$this->baseUrl}{$slug}/trang-{$lastPage}/#list-chapter";
        $lastCrawler = $this->fetch($lastPageUrl, 'story');
        if (!$lastCrawler) {
            return 0;
        }

        $lastChapterLink = $lastCrawler->filter('#list-chapter ul.list-chapter li')->last();
        if (!$lastChapterLink->count()) {
            return 0;
        }

        $href = $lastChapterLink->filter('a')->attr('href');
        preg_match('/chuong-(\d+)/i', $href ?? '', $m);

        return isset($m[1]) ? (int) $m[1] : 0;
    }

    private function parseChapterList(Crawler $crawler, string $storyUrl): array
    {
        $urls = [];
        $crawler->filter('#list-chapter ul.list-chapter li a')->each(function ($node) use (&$urls) {
            $href = $node->attr('href');
            if ($href) {
                $urls[] = $href;
            }
        });

        // Lấy thêm các trang khác nếu có
        $pages = [];
        $crawler->filter('#list-chapter .pagination li a')->each(function ($node) use (&$pages) {
            $href = $node->attr('href');
            if ($href && preg_match('/trang-(\d+)/', $href, $m)) {
                $pages[(int) $m[1]] = $href;
            }
        });

        foreach ($pages as $pageNum => $pageUrl) {
            $pageCrawler = $this->fetch($pageUrl, 'story');
            if (!$pageCrawler) {
                continue;
            }
            $pageCrawler->filter('#list-chapter ul.list-chapter li a')->each(function ($node) use (&$urls) {
                $href = $node->attr('href');
                if ($href) {
                    $urls[] = $href;
                }
            });
        }

        return array_values(array_unique($urls));
    }

    /**
     * Làm sạch nội dung chương: bỏ quảng cáo, script inject
     */
    private function cleanContent(string $html): string
    {
        // Xóa các thẻ script, style
        $html = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $html);
        $html = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $html);

        // Xóa các div quảng cáo kiểu truyenfull
        $html = preg_replace('/<div[^>]+(?:ads?|quangcao|banner)[^>]*>.*?<\/div>/is', '', $html);

        return trim($html);
    }
}
