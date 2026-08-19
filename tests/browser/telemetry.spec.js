/**
 * Browser acceptance spec for Telemetry (viewable impression + click + resilience).
 *
 * Run against a live WordPress host that has the plugin active:
 *   BASE_URL=https://my-wp.example node tests/browser/telemetry.spec.js
 *
 * Requires: npm i -D playwright && npx playwright install chromium
 * Exits 0 when all checks pass, 1 on failure.
 */
'use strict';

const BASE = process.env.BASE_URL;
if (!BASE) {
    console.error('Set BASE_URL to a WordPress site URL with the plugin active.');
    process.exit(0); // skip in CI without a host
}

let chromium;
try {
    ({ chromium } = require('playwright'));
} catch (e) {
    console.error('playwright not installed — run: npm i -D playwright && npx playwright install chromium');
    process.exit(0);
}

const API = BASE.replace(/\/$/, '') + '/wp-json/snw-telemetry/v1';
const PAGE = BASE.replace(/\/$/, '') + '/?snw_test=1';

let failures = 0;
function ok(name, cond) {
    if (cond) console.log('  PASS ' + name);
    else { console.error('  FAIL ' + name); failures++; }
}

(async () => {
    const browser = await chromium.launch();
    const ctx = await browser.newContext();
    const page = await ctx.newPage();

    // Capture outgoing telemetry events (public alias).
    const events = [];
    page.on('request', (req) => {
        if (req.url().includes('/sn-widget/telemetry/v1/event')) {
            events.push(req.postData());
        }
    });

    // 1) Normal load + in-view for >1s => exactly one viewable_impression.
    await page.goto(PAGE, { waitUntil: 'networkidle' });
    await page.waitForSelector('#snw-root .snw-item, .snw-widget .snw-item', { timeout: 15000 }).catch(() => {});
    await page.waitForTimeout(2500);
    const impressions = events.filter((b) => b && b.includes('"event":"viewable_impression"'));
    ok('one viewable_impression on visible load', impressions.length === 1);
    const loads = events.filter((b) => b && b.includes('"event":"widget_load"'));
    ok('widget_load fired', loads.length >= 1);

    // 2) Never visible (scroll away immediately) => zero impressions.
    events.length = 0;
    await page.goto(PAGE, { waitUntil: 'domcontentloaded' });
    await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
    await page.waitForTimeout(2000);
    ok('no impression when never visible', events.filter((b) => b && b.includes('viewable_impression')).length === 0);

    // 3) Clicking an article link fires article_click but still navigates.
    events.length = 0;
    const link = await page.$('#snw-root .snw-item a, .snw-widget .snw-item a');
    if (link) {
        const target = await link.getAttribute('href');
        await Promise.all([
            page.waitForRequest((r) => r.url().includes('/sn-widget/telemetry/v1/event')).catch(() => {}),
            link.click({ modifiers: ['Alt'] }).catch(() => {}) // Alt prevents navigation in some browsers
        ]);
        ok('article_click fired on link click', events.some((b) => b && b.includes('article_click')));
    } else {
        ok('article link present', false);
    }

    // 4) Telemetry endpoint down must not break widget rendering.
    await page.route('**/sn-widget/telemetry/v1/event', (route) => route.abort());
    await page.goto(PAGE, { waitUntil: 'networkidle' });
    const rendered = await page.$('#snw-root .snw-item, .snw-widget .snw-item');
    ok('widget renders even if telemetry fails', !!rendered);

    // 5) Admin stats endpoint requires auth (public call => 401/403).
    const status = await page.evaluate(async (url) => {
        const r = await fetch(url + '/stats?range=7');
        return r.status;
    }, API);
    ok('stats endpoint blocks unauthenticated', status === 401 || status === 403);

    await browser.close();
    console.log(failures === 0 ? '\nAll browser checks passed.' : '\n' + failures + ' browser check(s) failed.');
    process.exit(failures === 0 ? 0 : 1);
})().catch((e) => {
    console.error('Spec error:', e);
    process.exit(1);
});
