import { timingSafeEqual } from 'node:crypto';

export function authorized(value, secret) {
  if (!secret || secret.length < 32 || typeof value !== 'string') return false;
  const expected = Buffer.from(`Bearer ${secret}`);
  const actual = Buffer.from(value);
  return actual.length === expected.length && timingSafeEqual(actual, expected);
}

const identityDomains = ['microsoftonline.com', 'microsoftonline-p.com', 'microsoft.com', 'msauth.net',
  'msftauth.net', 'msauthimages.net', 'msftauthimages.net', 'live.com', 'office.com', 'office365.com',
  'ulo.pt', 'ipleiria.pt'];
const assetDomains = ['windows.net', 'microsoftazuread-sso.com', 'azureedge.net', 'msecnd.net',
  'fonts.googleapis.com', 'fonts.gstatic.com', 'cdn.jsdelivr.net'];

export function allowedUrl(value, baseUrl, navigation = false) {
  try {
    const url = new URL(value);
    if (url.protocol !== 'https:' || url.username || url.password || (url.port && url.port !== '443')) return false;
    const base = new URL(baseUrl);
    const domains = navigation ? identityDomains : [...identityDomains, ...assetDomains];
    return url.hostname === base.hostname || domains.some(domain => url.hostname === domain || url.hostname.endsWith(`.${domain}`));
  } catch { return false; }
}

export function validateLaunch(value, baseUrl) {
  const url = new URL(value);
  const base = new URL(baseUrl);
  if (url.origin !== base.origin || url.pathname !== `${base.pathname.replace(/\/$/, '')}/admin/tool/mobile/launch.php`
    || url.searchParams.get('service') !== 'moodle_mobile_app'
    || !/^[A-Za-z0-9]{64}$/.test(url.searchParams.get('passport') || '')
    || url.searchParams.get('confirmed') !== '1') throw new Error('Invalid launch');
  return url.href;
}

export function parseInput(value) {
  if (!value || typeof value !== 'object') throw new Error('Invalid input');
  if (value.type === 'click' && Number.isFinite(value.x) && Number.isFinite(value.y)
    && value.x >= 0 && value.x <= 1280 && value.y >= 0 && value.y <= 800) {
    return { type: 'click', x: value.x, y: value.y };
  }
  if (value.type === 'wheel' && Number.isFinite(value.deltaY)) return { type: 'wheel', deltaY: Math.max(-800, Math.min(800, value.deltaY)) };
  if (value.type === 'text' && typeof value.text === 'string' && value.text.length > 0 && value.text.length <= 8192) return { type: 'text', text: value.text };
  if (value.type === 'key' && ['Backspace', 'Delete', 'Tab', 'Shift+Tab', 'Enter', 'Escape', 'ArrowLeft', 'ArrowRight',
    'ArrowUp', 'ArrowDown', 'Home', 'End', 'Control+A'].includes(value.key)) return { type: 'key', key: value.key };
  throw new Error('Invalid input');
}
