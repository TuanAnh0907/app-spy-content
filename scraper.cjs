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
        ]
    });

    try {
        const page = await browser.newPage();

        // Giả lập trình duyệt thật
        await page.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36');
        await page.setExtraHTTPHeaders({ 'Accept-Language': 'vi-VN,vi;q=0.9,en-US;q=0.8' });

        await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 60000 });

        // Đợi JS render xong
        await new Promise(r => setTimeout(r, 6000));

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
