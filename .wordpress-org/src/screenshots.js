const puppeteer = require('puppeteer-core');
const OUT = process.argv[2];
const B = 'http://127.0.0.1:9401';
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
(async () => {
  const browser = await puppeteer.launch({ executablePath: '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome', headless: 'new', defaultViewport: { width: 1280, height: 860, deviceScaleFactor: 1 } });
  const page = await browser.newPage();
  await page.goto(B + '/wp-admin/', { waitUntil: 'networkidle0' });
  // Settings: link colour + underline so references read as links in the theme.
  await page.goto(B + '/wp-admin/options-general.php?page=bbm', { waitUntil: 'networkidle0' });
  for (const name of ['use_custom_color', 'underline_link']) {
    const box = await page.$(`input[name="bbm_options[${name}]"]`);
    if (box && !(await box.evaluate((e) => e.checked))) await box.click();
  }
  await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle0' }), page.click('#submit')]);
  await page.evaluate(() => { const n = document.querySelector('.notice, .updated'); if (n) n.remove(); window.scrollTo(0, 0); });
  await page.setViewport({ width: 1280, height: 1100 });
  await page.screenshot({ path: OUT + '/screenshot-1.png' });
  await page.setViewport({ width: 1280, height: 860 });
  // Post with references, tooltip open on the first one.
  await page.goto(B + '/?p=10', { waitUntil: 'networkidle0' });
  await page.evaluate(() => { const b = document.getElementById('wpadminbar'); if (b) b.remove(); document.documentElement.style.setProperty('margin-top', '0', 'important'); });
  const link = await page.$('.bbm-link[data-midvash-ref]');
  await link.hover();
  await page.waitForSelector('.bbm-tooltip--visible .bbm-tooltip__text', { timeout: 15000 });
  await sleep(800);
  await page.screenshot({ path: OUT + '/screenshot-2.png' });
  for (const [id, n] of [[11, 3], [12, 4]]) {
    await page.goto(B + '/?p=' + id, { waitUntil: 'networkidle0' });
    await page.evaluate(() => { const b = document.getElementById('wpadminbar'); if (b) b.remove(); document.documentElement.style.setProperty('margin-top', '0', 'important'); });
    await sleep(500);
    await page.screenshot({ path: OUT + '/screenshot-' + n + '.png' });
  }
  await browser.close();
})().catch((e) => { console.error(e); process.exit(1); });
