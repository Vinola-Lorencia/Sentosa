const fs = require('fs');
const path = require('path');
const { chromium } = require('playwright');

const HTML_DIR = path.join(__dirname, 'html');
const PNG_DIR = path.join(__dirname, 'png');

if (!fs.existsSync(PNG_DIR)) fs.mkdirSync(PNG_DIR, { recursive: true });

(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });

  const files = fs.readdirSync(HTML_DIR).filter((f) => f.endsWith('.html'));
  let count = 0;

  for (const file of files) {
    const name = file.replace(/\.html$/, '');
    const fileUrl = 'file://' + path.join(HTML_DIR, file).replace(/\\/g, '/');
    await page.goto(fileUrl, { waitUntil: 'load' });
    await page.screenshot({ path: path.join(PNG_DIR, `${name}.png`), fullPage: true });
    count++;
  }

  await browser.close();
  console.log(`Screenshotted ${count} pages to ${PNG_DIR}`);
})();
