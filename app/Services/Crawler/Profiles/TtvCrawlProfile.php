<?php

namespace App\Services\Crawler\Profiles;

use Psr\Http\Message\UriInterface;
use Spatie\Crawler\CrawlProfiles\CrawlProfile;

class TtvCrawlProfile extends CrawlProfile
{
    /**
     * Lọc các link được phép ĐƯA VÀO QUEUE để crawler duyệt qua
     */
    public function shouldCrawl(UriInterface $url): bool
    {
        if ($url->getHost() !== 'tangthuvien.net' && $url->getHost() !== 'www.tangthuvien.net') {
            return false;
        }

        $path = $url->getPath();
        $query = $url->getQuery(); // Ví dụ: page=2

        // 1. Cho phép cào trang chủ
        if ($path === '/' || $path === '') {
            return true;
        }

        // 2. Cho phép mọi link `/the-loai/...` (kể cả có query phân trang)
        if (str_starts_with($path, '/the-loai')) {
            return true;
        }

        // 3. Cho phép link `/doc-truyen/...` NHƯNG KHÔNG ĐƯỢC CÓ QUERY PHÂN TRANG
        // Việc này ngăn Crawler cặm cụi đọc hàng trăm trang nội dung chương truyện
        if (str_starts_with($path, '/doc-truyen')) {
            if (!empty($query)) {
                return false; // Chứa query (ví dụ: ?page=2) -> bỏ qua
            }
            return true;
        }

        // Còn lại các link rác khác, trang thông tin, hoặc danh sách kiểu khác (có thể mở rộng thêm) -> bỏ qua
        return false;
    }
}
