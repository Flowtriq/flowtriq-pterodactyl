const puppeteer = require('puppeteer');
const path = require('path');

(async () => {
    const browser = await puppeteer.launch({
        headless: 'new',
        executablePath: '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
        args: ['--no-sandbox', '--disable-setuid-sandbox']
    });

    const pages = [
        { file: 'settings.html', output: 'ptero-settings.png', width: 1440, height: 900 },
        { file: 'nodes.html', output: 'ptero-nodes.png', width: 1440, height: 600 },
        { file: 'node-detail.html', output: 'ptero-node-detail.png', width: 1440, height: 1100 },
        { file: 'ddos-tab.html', output: 'ptero-ddos-tab.png', width: 1440, height: 750 },
    ];

    for (const pg of pages) {
        const page = await browser.newPage();
        await page.setViewport({ width: pg.width, height: pg.height, deviceScaleFactor: 2 });

        const filePath = path.resolve(__dirname, pg.file);
        await page.goto(`file://${filePath}`, { waitUntil: 'networkidle2', timeout: 30000 });

        // Wait for CSS to fully load
        await new Promise(r => setTimeout(r, 2000));

        const outputPath = path.resolve(__dirname, pg.output);
        await page.screenshot({ path: outputPath, fullPage: false });
        console.log(`Captured: ${pg.output}`);
        await page.close();
    }

    await browser.close();
    console.log('\nAll screenshots saved to:', path.resolve(__dirname));
})();
