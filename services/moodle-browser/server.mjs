import http from 'node:http';
import { randomBytes } from 'node:crypto';
import { chromium } from 'playwright';
import { authorized, allowedUrl, validateLaunch, parseInput } from './security.mjs';

const secret = process.env.MOODLE_BROWSER_SECRET || '';
const baseUrl = process.env.MOODLE_BASE_URL || 'https://ead.ulo.pt/2026-27';
if (secret.length < 32) throw new Error('MOODLE_BROWSER_SECRET is required');
const sessions = new Map();
let browser;
let launching;
let creating = 0;
const ttl = 15 * 60 * 1000;
async function getBrowser() {
  if (browser?.isConnected()) return browser;
  if (!launching) launching = chromium.launch({ headless: process.env.BROWSER_HEADLESS !== 'false', args: ['--disable-dev-shm-usage'] })
    .then(value => { browser = value; return value; }).finally(() => { launching = null; });
  return launching;
}
async function destroy(id) {
  const session = sessions.get(id);
  if (!session) return;
  sessions.delete(id);
  session.payload = null;
  await session.context.close().catch(() => {});
}
const timer = setInterval(() => {
  for (const [id, session] of sessions) if (Date.now() > session.expires) void destroy(id);
}, 10000);
timer.unref();

function json(res, status, data) {
  res.writeHead(status, { 'Content-Type': 'application/json', 'Cache-Control': 'no-store' });
  res.end(JSON.stringify(data));
}
async function body(req) {
  let bytes = 0;
  const chunks = [];
  for await (const chunk of req) {
    bytes += chunk.length;
    if (bytes > 16384) throw new Error('Request too large');
    chunks.push(chunk);
  }
  return JSON.parse(Buffer.concat(chunks).toString());
}

async function inspect(session) {
  if (session.phase === 'ready') return;
  const pages = session.context.pages().filter(page => !page.isClosed());
  session.page = pages.at(-1) || session.page;
  for (const page of pages) {
    const current = new URL(page.url());
    const base = new URL(baseUrl);
    if (current.origin !== base.origin) continue;
    if (current.pathname.endsWith('/admin/tool/mobile/launch.php')) {
      const payload = await page.locator('a#launchapp').getAttribute('href', { timeout: 500 }).catch(() => null);
      if (payload && /^(?:moodlemobile|web\+studyos):\/\/token=[A-Za-z0-9+/=]+$/.test(payload) && payload.length <= 2048) {
        session.payload = payload;
        session.phase = 'ready';
        return;
      }
    } else if (!current.pathname.includes('/login/') && !current.pathname.includes('/auth/')
      && !session.resuming && await page.locator('.usermenu [data-region="user-menu-toggle"], .usermenu .userbutton, [data-region="user-menu"]').count()) {
      // Some institutional SSO providers land on the dashboard instead of wantsurl.
      session.resuming = true;
      await page.goto(session.launch, { waitUntil: 'domcontentloaded', timeout: 30000 }).catch(() => { session.phase = 'error'; });
      session.resuming = false;
    }
  }
}

const server = http.createServer(async (req, res) => {
  if ((req.url === '/health' || req.url === '/up') && req.method === 'GET') return json(res, 200, { status: 'ok' });
  if (!authorized(req.headers.authorization, secret)) return json(res, 401, { error: 'Unauthorized' });
  try {
    if (req.url === '/sessions' && req.method === 'POST') {
      if (sessions.size + creating >= 2) return json(res, 429, { error: 'Capacity reached' });
      const data = await body(req);
      const launch = validateLaunch(data.launch, baseUrl);
      creating++;
      let context;
      try {
        context = await (await getBrowser()).newContext({ viewport: { width: 1280, height: 800 }, locale: 'pt-PT', acceptDownloads: false });
        await context.route('**/*', async route => {
          const request = route.request();
          if (!allowedUrl(request.url(), baseUrl, request.isNavigationRequest())) return route.abort();
          return route.continue();
        });
        const page = await context.newPage();
        const id = randomBytes(32).toString('hex');
        const session = { context, page, launch, phase: 'authenticating', payload: null, expires: Date.now() + ttl, chain: Promise.resolve(), resuming: false };
        sessions.set(id, session);
        context.on('page', popup => { session.page = popup; });
        // Navigation may take time; return the isolated session immediately.
        void page.goto(launch, { waitUntil: 'domcontentloaded', timeout: 45000 }).catch(() => { session.phase = 'error'; });
        return json(res, 201, { id });
      } catch {
        await context?.close().catch(() => {});
        return json(res, 503, { error: 'Browser unavailable' });
      } finally { creating--; }
    }
    const match = /^\/sessions\/([a-f0-9]{64})(?:\/(status|frame|input))?$/.exec(req.url || '');
    if (!match) return json(res, 404, { error: 'Not found' });
    const [, id, action] = match;
    const session = sessions.get(id);
    if (!session || Date.now() > session.expires) {
      await destroy(id);
      return json(res, 410, { error: 'Session expired' });
    }
    if (!action && req.method === 'DELETE') {
      await destroy(id);
      return json(res, 200, { status: 'closed' });
    }
    if (action === 'status' && req.method === 'GET') {
      await inspect(session);
      let origin = '';
      try { origin = new URL(session.page.url()).origin; } catch {}
      return json(res, 200, { phase: session.phase, origin, ...(session.phase === 'ready' ? { payload: session.payload } : {}) });
    }
    if (action === 'frame' && req.method === 'GET') {
      if (session.phase !== 'authenticating') return json(res, 409, { error: 'Browser not active' });
      const frame = await session.page.screenshot({ type: 'jpeg', quality: 65, timeout: 5000 });
      res.writeHead(200, { 'Content-Type': 'image/jpeg', 'Cache-Control': 'no-store' });
      return res.end(frame);
    }
    if (action === 'input' && req.method === 'POST') {
      if (session.phase !== 'authenticating') return json(res, 409, { error: 'Browser not active' });
      const input = parseInput(await body(req));
      const operation = session.chain.then(async () => {
        const page = session.page;
        if (input.type === 'click') await page.mouse.click(input.x, input.y);
        if (input.type === 'wheel') await page.mouse.wheel(0, input.deltaY);
        if (input.type === 'text') await page.keyboard.insertText(input.text);
        if (input.type === 'key') await page.keyboard.press(input.key);
      });
      session.chain = operation.catch(() => {});
      await operation;
      return json(res, 200, { status: 'ok' });
    }
    return json(res, 405, { error: 'Method not allowed' });
  } catch {
    // Never log request bodies, input, cookies, launch URLs or authentication payloads.
    if (!res.headersSent) json(res, 502, { error: 'Browser operation failed' });
    else res.end();
  }
});
server.requestTimeout = 60000;
server.listen(Number(process.env.PORT || 3000), '::', () => console.log('Managed Moodle browser ready'));
async function shutdown() {
  server.close();
  for (const id of sessions.keys()) await destroy(id);
  await browser?.close();
  process.exit(0);
}
process.on('SIGTERM', shutdown);
process.on('SIGINT', shutdown);
