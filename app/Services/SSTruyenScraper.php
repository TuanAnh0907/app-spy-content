<?php

namespace App\Services;

use Symfony\Component\DomCrawler\Crawler;

/**
 * Scraper cho sstruyen.vn
 * Cấu trúc URL truyện: https://sstruyen.vn/truyen/{slug}/
 * Chương: https://sstruyen.vn/truyen/{slug}/chuong-{n}/
 */
class SSTruyenScraper extends BaseScraperService
{
    protected string $source  = 'sstruyen';
    protected string $baseUrl = 'https://sstruyen.vn';

    public function scrapeStory(string $url): ?array
    {
        $crawler = $this->fetch($url, 'story');
        if (!$crawler) {
            return null;
        }

        try {
            $title = $crawler->filter('h1.name-title, .book h1')->count()
                ? trim($crawler->filter('h1.name-title, .book h1')->first()->text(''))
                : '';

            $author = $crawler->filter('.info a[href*="tac-gia"], .author a')->count()
                ? trim($crawler->filter('.info a[href*="tac-gia"], .author a')->first()->text(''))
                : null;

            $description = $crawler->filter('#summary, .desc-text')->count()
                ? trim($crawler->filter('#summary, .desc-text')->first()->text(''))
                : null;

            $coverUrl = $crawler->filter('.book img, .book-img img')->count()
                ? $crawler->filter('.book img, .book-img img')->first()->attr('src')
                : null;

            // Genres
            $genres = [];
            $crawler->filter('.info a[href*="the-loai"]')->each(function ($node) use (&$genres) {
                $genres[] = trim($node->text());
            });

            // Status
            $statusText = $crawler->filter('.info .text-success, .info .text-primary, .info .status')->count()
                ? $crawler->filter('.info .text-success, .info .text-primary, .info .status')->first()->text('')
                : '';
            $status     = str_contains(mb_strtolower($statusText), 'hoàn') ? 'completed' : 'ongoing';

            // source_id từ slug
            $path     = rtrim(parse_url($url, PHP_URL_PATH), '/');
            $parts    = explode('/', trim($path, '/'));
            $sourceId = end($parts);

            // Danh sách chương
            $chapterUrls   = $this->parseChapterList($crawler, $url);
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
            \Illuminate\Support\Facades\Log::error("[{$this->source}] Parse story error: {$url} — ".$e->getMessage());
            return null;
        }
    }

    public function scrapeChapter(string $url): ?array
    {
        $crawler = $this->fetch($url, 'chapter');
        if (!$crawler) {
            return null;
        }

        try {
            $title = $crawler->filter('.chapter-title, h2.title-chuong')->count()
                ? trim($crawler->filter('.chapter-title, h2.title-chuong')->first()->text(''))
                : null;

            preg_match('/chuong-(\d+(?:-\d+)?)/i', $url, $m);
            $chapterNumber = isset($m[1]) ? (int) explode('-', $m[1])[0] : 0;

            $content = '';
            if ($crawler->filter('#chapter-c, .chapter-c, .noi-dung-chuong')->count()) {
                $content = $crawler->filter('#chapter-c, .chapter-c, .noi-dung-chuong')->first()->html();
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
            \Illuminate\Support\Facades\Log::error("[{$this->source}] Parse chapter error: {$url} — ".$e->getMessage());
            return null;
        }
    }

    public function getStoryListUrls(int $page = 1): array
    {
        $url     = "{$this->baseUrl}/truyen-moi-cap-nhat/trang-{$page}/";
        $crawler = $this->fetch($url, 'story');
        if (!$crawler) {
            return [];
        }

        $urls = [];
        $crawler->filter('.list-truyen .row h3.truyen-title a, .list-story .story-item h3 a')->each(function ($node) use
        (
            &$urls
        ) {
            $href = $node->attr('href');
            if ($href) {
                $urls[] = $href;
            }
        });

        return $urls;
    }

    // ─── Private helpers ─────────────────────────────────────────────────────

    private function parseChapterList(Crawler $crawler, string $storyUrl): array
    {
        $urls = [];

        $crawler->filter('#list-chapter ul.list-chapter li a, .list-chapter li a')->each(function ($node) use (&$urls) {
            $href = $node->attr('href');
            if ($href) {
                $urls[] = rtrim($href, '/');
            }
        });

        // Phân trang chương
        $pages = [];
        $crawler->filter('#list-chapter .pagination li a')->each(function ($node) use (&$pages) {
            $href = $node->attr('href');
            if ($href && preg_match('/trang-(\d+)/', $href, $m)) {
                $pages[(int) $m[1]] = $href;
            }
        });

        foreach ($pages as $pageUrl) {
            $pageCrawler = $this->fetch($pageUrl, 'story');
            if (!$pageCrawler) {
                continue;
            }
            $pageCrawler->filter('#list-chapter ul.list-chapter li a')->each(function ($node) use (&$urls) {
                $href = $node->attr('href');
                if ($href) {
                    $urls[] = rtrim($href, '/');
                }
            });
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
