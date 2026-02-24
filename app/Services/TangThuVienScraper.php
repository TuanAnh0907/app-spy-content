<?php

namespace App\Services;

use App\Models\ScrapeLog;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Scraper cho tangthuvien.net
 * Cấu trúc URL: https://tangthuvien.net/doc-truyen/{slug}
 * Chương: https://tangthuvien.net/doc-truyen/{slug}/chuong-{n}
 */
class TangThuVienScraper extends BaseScraperService
{
    protected string $source  = 'tangthuvien';
    protected string $baseUrl = 'https://tangthuvien.net';

    public function scrapeStory(string $url): ?array
    {
        $crawler = $this->fetch($url, 'story');
        if (!$crawler) return null;

        try {
            $title = $crawler->filter('.story-title h1')->count()
                ? trim($crawler->filter('.story-title h1')->first()->text(''))
                : trim($crawler->filter('h1.title')->first()->text(''));

            $author = $crawler->filter('.author a')->count()
                ? trim($crawler->filter('.author a')->first()->text(''))
                : null;

            $description = $crawler->filter('.story-detail-info .summary p')->count()
                ? trim($crawler->filter('.story-detail-info .summary p')->first()->text(''))
                : ($crawler->filter('#story-detail .desc-text')->count()
                    ? trim($crawler->filter('#story-detail .desc-text')->first()->text(''))
                    : null);

            $coverUrl = $crawler->filter('.book img')->count()
                ? $crawler->filter('.book img')->first()->attr('src')
                : null;

            // Genres
            $genres = [];
            $crawler->filter('.story-detail-info .genre a, .info a[href*="the-loai"]')->each(function ($node) use (&$genres) {
                $genres[] = trim($node->text());
            });

            // Status
            $statusText = $crawler->filter('.story-detail-info .status, .info .text-primary')->count()
                ? $crawler->filter('.story-detail-info .status, .info .text-primary')->first()->text('')
                : '';
            $status = str_contains(mb_strtolower($statusText), 'hoàn') ? 'completed' : 'ongoing';

            // source_id từ slug URL
            $path     = rtrim(parse_url($url, PHP_URL_PATH), '/');
            $sourceId = basename($path);

            // Lấy danh sách chương
            $chapterUrls  = $this->parseChapterList($crawler, $url, $sourceId);
            $totalChapters = count($chapterUrls);

            return [
                'source'         => $this->source,
                'source_id'      => $sourceId,
                'source_url'     => $url,
                'title'          => $title,
                'author'         => $author,
                'description'    => $description,
                'cover_url'      => $coverUrl,
                'status'         => $status,
                'genres'         => $genres,
                'total_chapters' => $totalChapters,
                'chapter_urls'   => $chapterUrls,
            ];

        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error("[{$this->source}] Parse story error: {$url} — " . $e->getMessage());
            return null;
        }
    }

    public function scrapeChapter(string $url): ?array
    {
        $crawler = $this->fetch($url, 'chapter');
        if (!$crawler) return null;

        try {
            $title = $crawler->filter('.chapter-title h2, .box-chap h3')->count()
                ? trim($crawler->filter('.chapter-title h2, .box-chap h3')->first()->text(''))
                : null;

            // Số chương từ URL: /doc-truyen/{slug}/chuong-{n}
            preg_match('/chuong-(\d+(?:-\d+)?)/i', $url, $m);
            $chapterNumber = isset($m[1]) ? (int) explode('-', $m[1])[0] : 0;

            // Nội dung chương
            $content = '';
            if ($crawler->filter('.box-chap')->count()) {
                $content = $crawler->filter('.box-chap')->first()->html();
            } elseif ($crawler->filter('#chapter-c')->count()) {
                $content = $crawler->filter('#chapter-c')->first()->html();
            }

            $content   = $this->cleanContent($content);
            $wordCount = str_word_count(strip_tags($content));

            return [
                'source_url'     => $url,
                'chapter_number' => $chapterNumber,
                'title'          => $title,
                'content'        => $content,
                'word_count'     => $wordCount,
            ];

        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error("[{$this->source}] Parse chapter error: {$url} — " . $e->getMessage());
            return null;
        }
    }

    public function getStoryListUrls(int $page = 1): array
    {
        $url     = "{$this->baseUrl}/tong-hop?page={$page}";
        $crawler = $this->fetch($url, 'story');
        if (!$crawler) return [];

        $urls = [];
        $crawler->filter('.list-story .story-item a.story-name, .list-truyen .row h3.truyen-title a')->each(function ($node) use (&$urls) {
            $href = $node->attr('href');
            if ($href) $urls[] = $href;
        });

        return $urls;
    }

    // ─── Private helpers ─────────────────────────────────────────────────────

    private function parseChapterList(Crawler $crawler, string $storyUrl, string $slug): array
    {
        $urls = [];

        // Thử lấy từ trang hiện tại
        $crawler->filter('#list-chapter ul.list-chapter li a, .list-chapter li a')->each(function ($node) use (&$urls) {
            $href = $node->attr('href');
            if ($href) $urls[] = rtrim($href, '/');
        });

        // Nếu có phân trang, lấy API chapter list (tangthuvien có endpoint riêng)
        if (empty($urls)) {
            // Thử endpoint AJAX của tangthuvien
            $storyId = null;
            $crawler->filter('input[name="story"], [data-story-id]')->each(function ($node) use (&$storyId) {
                $storyId = $node->attr('value') ?? $node->attr('data-story-id');
            });

            if ($storyId) {
                $apiUrl    = "{$this->baseUrl}/doc-truyen/chapter-list?story_id={$storyId}&page=all";
                $apiCrawler = $this->fetch($apiUrl, 'story');
                if ($apiCrawler) {
                    $apiCrawler->filter('li a')->each(function ($node) use (&$urls) {
                        $href = $node->attr('href');
                        if ($href) $urls[] = rtrim($href, '/');
                    });
                }
            }
        }

        return array_values(array_unique($urls));
    }

    private function cleanContent(string $html): string
    {
        $html = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $html);
        $html = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $html);
        $html = preg_replace('/<div[^>]+(?:ads?|quangcao|banner)[^>]*>.*?<\/div>/is', '', $html);
        return trim($html);
    }
}
