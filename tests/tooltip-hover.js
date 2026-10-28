/**
 * Teste do tooltip com mouse lento e toque (puppeteer-core + Chrome instalado).
 *
 * Sobe um WordPress com o plugin e um post (id 20) com uma referência no topo
 * (tooltip abaixo) e outra no fim (tooltip acima), na porta 9402, e roda:
 *   npm i --no-save puppeteer-core && node tests/tooltip-hover.js <dir-dos-prints>
 * Leva o mouse do link até "Read more" em 2 s e confere que o tooltip fica
 * aberto; no celular, 1º toque abre, toque fora fecha, 2º toque segue o link.
 */
const puppeteer = require('puppeteer-core');
const URL = 'http://127.0.0.1:9402/?p=20';
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const results = [];
const check = (name, ok, extra = '') => results.push(`${ok ? 'OK  ' : 'FAIL'} ${name}${extra ? ' (' + extra + ')' : ''}`);

async function center(page, el) {
  const b = await el.boundingBox();
  return { x: b.x + b.width / 2, y: b.y + b.height / 2 };
}
async function slowMove(page, from, to, steps, ms) {
  for (let i = 1; i <= steps; i++) {
    await page.mouse.move(from.x + ((to.x - from.x) * i) / steps, from.y + ((to.y - from.y) * i) / steps);
    await sleep(ms);
  }
}
const visible = (page) => page.evaluate(() => { const t = document.querySelector('.bbm-tooltip'); return !!t && t.classList.contains('bbm-tooltip--visible'); });

(async () => {
  const browser = await puppeteer.launch({ executablePath: '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome', headless: 'new' });
  const page = await browser.newPage();
  await page.setViewport({ width: 1280, height: 800 });
  await page.goto(URL, { waitUntil: 'networkidle0' });
  await page.evaluate(() => { const b = document.getElementById('wpadminbar'); if (b) b.remove(); });

  for (const [ref, where] of [['Romans 8:28', 'below'], ['John 3:16', 'above']]) {
    const link = await page.$(`.bbm-link[data-midvash-ref="${ref}"]`);
    await link.evaluate((el, w) => el.scrollIntoView({ block: w === 'above' ? 'end' : 'start' }), where);
    await page.evaluate((w) => window.scrollBy(0, w === 'above' ? 40 : -80), where);
    await page.mouse.move(5, 5);
    await sleep(500);
    const c = await center(page, link);
    await slowMove(page, { x: c.x - 60, y: c.y }, c, 6, 20);
    await page.waitForSelector('.bbm-tooltip--visible .bbm-tooltip__text', { timeout: 15000 });
    const cls = await page.evaluate(() => document.querySelector('.bbm-tooltip').className);
    check(`${ref}: tooltip ${where} the link`, cls.includes('bbm-tooltip--' + where), cls);
    const attrs = await link.evaluate((el) => [el.getAttribute('title'), el.getAttribute('aria-label')]);
    check(`${ref}: no native title, aria-label kept`, attrs[0] === null && !!attrs[1], JSON.stringify(attrs));
    const more = await page.$('.bbm-tooltip .bbm-tooltip__read-more');
    const m = await center(page, more);
    // Slow: 50 steps x 40 ms = 2 s from the link to "Read more".
    await slowMove(page, c, m, 50, 40);
    await sleep(500);
    check(`${ref}: still open after a slow move to "Read more"`, await visible(page));
    const hoverOk = await page.evaluate(() => document.querySelector('.bbm-tooltip__read-more').matches(':hover'));
    check(`${ref}: mouse is on "Read more"`, hoverOk);
    await page.mouse.move(5, 5);
    await sleep(800);
    check(`${ref}: closes after leaving`, !(await visible(page)));
  }

  // Phone
  const phone = await browser.newPage();
  await phone.emulate(puppeteer.KnownDevices['iPhone 13']);
  await phone.goto(URL, { waitUntil: 'networkidle0' });
  await phone.evaluate(() => { const b = document.getElementById('wpadminbar'); if (b) b.remove(); });
  const plink = await phone.$('.bbm-link[data-midvash-ref="Romans 8:28"]');
  const before = phone.url();
  const pages0 = (await browser.pages()).length;
  await plink.tap();
  await phone.waitForSelector('.bbm-tooltip--visible .bbm-tooltip__text', { timeout: 15000 });
  await sleep(400);
  check('phone: first tap opens the tooltip', await visible(phone));
  check('phone: first tap does not navigate', phone.url() === before && (await browser.pages()).length === pages0);
  await phone.screenshot({ path: process.argv[2] + '/toque-celular.png' });
  await phone.tap('h1');
  await sleep(800);
  check('phone: tap elsewhere closes', !(await visible(phone)));
  await plink.tap();
  await phone.waitForSelector('.bbm-tooltip--visible', { timeout: 15000 });
  await sleep(400);
  const newTab = new Promise((r) => browser.once('targetcreated', () => r(true)));
  await plink.tap();
  check('phone: second tap follows the link', await Promise.race([newTab, sleep(3000).then(() => false)]));

  console.log(results.join('\n'));
  await browser.close();
  process.exit(results.some((r) => r.startsWith('FAIL')) ? 1 : 0);
})().catch((e) => { console.log(results.join('\n')); console.log('ERRO: ' + e.message.split('\n')[0]); process.exit(1); });
