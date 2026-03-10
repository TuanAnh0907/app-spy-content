const puppeteer = require('puppeteer-extra');
const StealthPlugin = require('puppeteer-extra-plugin-stealth');
puppeteer.use(StealthPlugin());

const url = process.argv[2];

if (!url) {
    process.stderr.write("Usage: node scraper.cjs <url>\n");
    process.exit(1);
}

(async () => {
    const browser = await puppeteer.launch({
        headless: "new",
        args: [
            '--no-sandbox',
            '--disable-setuid-sandbox',
            '--disable-dev-shm-usage',
            '--disable-gpu',
            '--ignore-certificate-errors',
            '--disable-extensions',
        ]
    });

    try {
        const page = await browser.newPage();

        // Giả lập trình duyệt thật
        await page.setUserAgent('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36');
        await page.setExtraHTTPHeaders({
            'Accept-Language': 'vi-VN,vi;q=0.9,en-US;q=0.8',
            'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
            'Cache-Control': 'no-cache',
            'Pragma': 'no-cache',
            'Sec-Fetch-Dest': 'document',
            'Sec-Fetch-Mode': 'navigate',
            'Sec-Fetch-Site': 'none',
            'Sec-Fetch-User': '?1',
            'Upgrade-Insecure-Requests': '1'
        });

        await page.goto(url, { waitUntil: 'load', timeout: 60000 });

        // Cuộn xuống để load hết lazy elements
        await page.evaluate(async () => {
            await new Promise((resolve) => {
                let totalHeight = 0;
                let distance = 100;
                let timer = setInterval(() => {
                    let scrollHeight = document.body.scrollHeight;
                    window.scrollBy(0, distance);
                    totalHeight += distance;
                    if (totalHeight >= scrollHeight || totalHeight > 10000) {
                        clearInterval(timer);
                        resolve();
                    }
                }, 100);
            });
        });

        // Đợi thêm một chút cho chắc chắn
        await new Promise(r => setTimeout(r, 5000));

        const html = await page.content();
        process.stdout.write(html);
        process.exit(0);
    } catch (e) {
        process.stderr.write(e.message + '\n');
        process.exit(1);
    } finally {
        await browser.close();
    }
})();
