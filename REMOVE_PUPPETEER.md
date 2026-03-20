# Hướng dẫn gỡ bỏ toàn bộ Headless Browser & Crawler

## Dừng PM2 trước khi gỡ

```bash
# Dừng và xóa tất cả process PM2
pm2 delete crawler-thread1-discovery crawler-thread2-dispatcher crawler-thread2-worker
pm2 save
```

## Xóa các file Crawler

```bash
# Xóa script cào web
rm /var/www/read-app/spy-doctruyen/scraper.cjs
rm /var/www/read-app/spy-doctruyen/ecosystem.config.yml

# Xóa node_modules (puppeteer)
rm -rf /var/www/read-app/spy-doctruyen/node_modules
rm /var/www/read-app/spy-doctruyen/package.json
rm /var/www/read-app/spy-doctruyen/package-lock.json
```

## Gỡ PHP packages

```bash
cd /var/www/read-app/spy-doctruyen
composer remove spatie/browsershot spatie/crawler bensampo/laravel-enum
```

## Xóa code Laravel

```bash
rm app/Console/Commands/CrawlDtruyenCommand.php
rm app/Console/Commands/DispatchStoryJobsCommand.php
rm app/Jobs/ProcessDtruyenStoryJob.php
rm app/Jobs/CrawlChapterJob.php
rm app/Jobs/CrawlChapterPageJob.php
rm app/Models/DtruyenQueue.php
rm app/Models/DtruyenStory.php
rm app/Models/ScrapedChapter.php
rm app/Enums/DtruyenStoryType.php
rm -rf app/Services/Crawler/
```

## Xóa Database

```bash
php artisan migrate:rollback --step=5
# Hoặc xóa hẳn trong MySQL:
# DROP TABLE scraped_chapters, dtruyen_stories, dtruyen_queues;
```

## Xóa Logs & Storage

```bash
rm -rf storage/app/stories/
rm storage/logs/pm2-*.log
rm storage/logs/crawl.log
```
