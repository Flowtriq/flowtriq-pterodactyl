const puppeteer = require('puppeteer');
const path = require('path');

const BASE = 'http://139.177.197.144:8888';
const OUT = __dirname;
const SERVER_UUID = 'b9d996b8'; // SurvivalCraft SMP uuidShort

(async () => {
    const browser = await puppeteer.launch({
        headless: 'new',
        executablePath: '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
        args: ['--no-sandbox', '--disable-setuid-sandbox']
    });

    const page = await browser.newPage();
    await page.setViewport({ width: 1440, height: 900, deviceScaleFactor: 2 });

    // Step 1: Login via auto-login route
    console.log('Logging in...');
    await page.goto(`${BASE}/auth/auto-login`, { waitUntil: 'networkidle2', timeout: 30000 });
    await new Promise(r => setTimeout(r, 1000));
    console.log('Logged in, at:', page.url());

    // Screenshot 1: Settings page
    console.log('Capturing settings...');
    await page.goto(`${BASE}/admin/flowtriq/settings`, { waitUntil: 'networkidle2', timeout: 30000 });
    await new Promise(r => setTimeout(r, 2000));
    await page.screenshot({ path: path.join(OUT, 'real-settings.png'), fullPage: false });
    console.log('  -> real-settings.png');

    // Screenshot 2: Nodes list
    console.log('Capturing nodes...');
    await page.goto(`${BASE}/admin/flowtriq/nodes`, { waitUntil: 'networkidle2', timeout: 30000 });
    await new Promise(r => setTimeout(r, 2000));
    await page.screenshot({ path: path.join(OUT, 'real-nodes.png'), fullPage: false });
    console.log('  -> real-nodes.png');

    // Screenshot 3: Node detail (EU-West-01, node_id = 3)
    console.log('Capturing node detail...');
    await page.goto(`${BASE}/admin/flowtriq/nodes/3`, { waitUntil: 'networkidle2', timeout: 30000 });
    await new Promise(r => setTimeout(r, 2000));
    await page.setViewport({ width: 1440, height: 1200, deviceScaleFactor: 2 });
    await new Promise(r => setTimeout(r, 500));
    await page.screenshot({ path: path.join(OUT, 'real-node-detail.png'), fullPage: false });
    console.log('  -> real-node-detail.png');

    // Screenshot 4: Server DDoS tab (server owner view)
    console.log('Capturing DDoS tab...');
    await page.setViewport({ width: 1440, height: 900, deviceScaleFactor: 2 });
    await page.goto(`${BASE}/api/flowtriq/servers/${SERVER_UUID}/ddos/view`, { waitUntil: 'networkidle2', timeout: 30000 });
    await new Promise(r => setTimeout(r, 2000));
    console.log('  DDoS tab URL:', page.url());
    await page.screenshot({ path: path.join(OUT, 'real-ddos-tab.png'), fullPage: false });
    console.log('  -> real-ddos-tab.png');

    await browser.close();
    console.log('\nAll screenshots saved to:', OUT);
})();
