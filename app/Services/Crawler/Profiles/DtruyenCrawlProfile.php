<?php

namespace App\Services\Crawler\Profiles;

use Psr\Http\Message\UriInterface;
use Spatie\Crawler\CrawlProfiles\CrawlProfile;

class DtruyenCrawlProfile extends CrawlProfile
{
    /**
     * Lọc các link được phép đưa vào queue để crawler duyệt qua.
     * Chỉ cho phép crawl trong domain truyencom.com.
     */
    public function shouldCrawl(UriInterface $url): bool
    {
        $host = $url->getHost();
        if (!str_contains($host, 'truyencom.com') && !str_contains($host, 'dtruyen.com')) {
            return false;
        }

        $path  = $url->getPath();
        $query = $url->getQuery();

        // Trang chủ
        if ($path === '/' || $path === '') {
            return true;
        }

        // Trang thể loại (kể cả phân trang)
        if (str_starts_with($path, '/the-loai')) {
            return true;
        }

        // Trang truyện (chỉ trang tổng, không vào trang chương để tránh cào hàng ngàn trang)
        if (preg_match('/^\/[a-z0-9\-]+\.[0-9]+$/', rtrim($path, '/'))) {
            return empty($query);
        }

        return false;
    }
}
