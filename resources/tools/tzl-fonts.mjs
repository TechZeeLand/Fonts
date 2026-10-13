#!/usr/bin/env node
/**
 * TZL Fonts cache helper (Node 18+, no dependencies).
 *
 * Downloads fonts from {{BASE_URL}} into a folder of your project, rewrites the CSS
 * to point at your own copies and re-checks the service only when the cache is older
 * than the TTL (conditional request, so unchanged fonts are not downloaded again).
 *
 *   node tzl-fonts.mjs "Alata:wght@400;700" "Another Font" --out public/fonts --url /fonts
 *
 * Options
 *   --out <dir>       where to write files            (default: fonts)
 *   --url <path>      public URL path of that folder  (default: /fonts)
 *   --ttl <time>      refresh interval, e.g. 12h, 3d  (default: {{CACHE_DAYS}}d)
 *   --display <v>     font-display                    (default: swap)
 *   --name <name>     output basename                 (default: tzl-fonts)
 *   --legacy          also keep WOFF/TTF fallbacks
 *   --force           refresh now, ignore the TTL
 *   --base <url>      use another TZL Fonts server    (default: {{BASE_URL}})
 */
import { mkdir, readFile, writeFile, rename, rm, access } from 'node:fs/promises';
import path from 'node:path';

const DEFAULT_BASE = process.env.TZL_FONTS_BASE || '{{BASE_URL}}';
const DEFAULT_TTL = '{{CACHE_DAYS}}d';

function parseArgs(argv) {
  const o = { families: [], out: 'fonts', url: '/fonts', ttl: DEFAULT_TTL, display: 'swap', name: 'tzl-fonts', legacy: false, force: false, base: DEFAULT_BASE, help: false };
  for (let i = 0; i < argv.length; i++) {
    const a = argv[i];
    const val = () => { if (i + 1 >= argv.length) { throw new Error(`Missing value for ${a}`); } return argv[++i]; };
    if (a === '--help' || a === '-h') o.help = true;
    else if (a === '--force') o.force = true;
    else if (a === '--legacy') o.legacy = true;
    else if (a === '--out') o.out = val();
    else if (a === '--url') o.url = val();
    else if (a === '--ttl') o.ttl = val();
    else if (a === '--display') o.display = val();
    else if (a === '--name') o.name = val();
    else if (a === '--base') o.base = val();
    else if (a.startsWith('--')) throw new Error(`Unknown option ${a}`);
    else o.families.push(a);
  }
  return o;
}

function parseTtl(s) {
  const m = /^(\d+)\s*(s|m|h|d)?$/.exec(String(s).trim());
  if (!m) throw new Error(`Invalid --ttl "${s}" (examples: 90m, 12h, 3d)`);
  return Number(m[1]) * { s: 1e3, m: 6e4, h: 36e5, d: 864e5 }[m[2] || 's'];
}

const exists = (p) => access(p).then(() => true, () => false);

async function readJson(p) {
  try { return JSON.parse(await readFile(p, 'utf8')); } catch { return null; }
}

async function atomicWrite(file, data) {
  const tmp = `${file}.${process.pid}.tmp`;
  await writeFile(tmp, data);
  await rename(tmp, file);
}

async function get(url, etag) {
  const headers = { 'User-Agent': 'tzl-fonts-cache/1.0' };
  if (etag) headers['If-None-Match'] = etag;
  return fetch(url, { headers, signal: AbortSignal.timeout(20000) });
}

export async function run(argv, log = console.log) {
  const o = parseArgs(argv);
  if (o.help || o.families.length === 0) {
    log('Usage: node tzl-fonts.mjs "Family:wght@400;700" ["Another Family"] [--out dir] [--url /fonts] [--ttl 7d] [--force]');
    return o.help ? 0 : 1;
  }
  const base = o.base.replace(/\/+$/, '');
  const ttl = parseTtl(o.ttl);
  const out = path.resolve(o.out);
  const urlPrefix = o.url.replace(/\/+$/, '');
  const cssFile = path.join(out, `${o.name}.css`);
  const lockFile = path.join(out, `${o.name}.lock.json`);

  const query = o.families.map((f) => 'family=' + encodeURIComponent(f).replace(/%20/g, '+')).join('&')
    + `&display=${encodeURIComponent(o.display)}` + (o.legacy ? '&legacy=1' : '');
  const cssUrl = `${base}/css2?${query}`;

  const lock = await readJson(lockFile);
  const same = lock && lock.source === cssUrl && lock.prefix === urlPrefix && (await exists(cssFile));
  if (same && !o.force && Date.now() - lock.fetched < ttl) {
    log(`TZL Fonts: cache is fresh (${Math.round((Date.now() - lock.fetched) / 36e5)}h old), nothing to do.`);
    return 0;
  }

  await mkdir(out, { recursive: true });
  let res;
  try {
    res = await get(cssUrl, same && !o.force ? lock.etag : '');
  } catch (err) {
    if (same) { log(`TZL Fonts: ${base} is unreachable (${err.message}); keeping the cached copy.`); return 0; }
    log(`TZL Fonts: could not reach ${base}: ${err.message}`);
    return 1;
  }

  if (res.status === 304 && same) {
    await atomicWrite(lockFile, JSON.stringify({ ...lock, fetched: Date.now() }, null, 2));
    log('TZL Fonts: no changes upstream, cache timer reset.');
    return 0;
  }
  if (res.status !== 200) {
    const msg = `TZL Fonts: ${base} answered ${res.status}. ${(await res.text()).slice(0, 200)}`;
    if (same) { log(msg + ' Keeping the cached copy.'); return 0; }
    log(msg);
    return 1;
  }

  let css = await res.text();
  const etag = res.headers.get('etag') || '';
  const origin = new URL(base).origin;
  const refs = [...new Set([...css.matchAll(/url\(\s*(['"]?)([^)'"]+)\1\s*\)/g)].map((m) => m[2]))];
  const files = [];
  for (const ref of refs) {
    let u;
    try { u = new URL(ref, base + '/'); } catch { continue; }
    if (u.origin !== origin) continue; // never fetch from other hosts
    const ext = path.extname(u.pathname).toLowerCase();
    if (!['.woff2', '.woff', '.ttf', '.otf'].includes(ext)) continue;
    const stem = path.basename(decodeURIComponent(u.pathname), ext);
    const v = u.searchParams.get('v') || '0';
    const local = `${stem}-${v}${ext}`.replace(/[^A-Za-z0-9._-]+/g, '_');
    const dest = path.join(out, local);
    if (!(await exists(dest))) {
      let fr;
      try { fr = await get(u.href); } catch (err) { log(`TZL Fonts: download failed for ${local}: ${err.message}`); return same ? 0 : 1; }
      if (fr.status !== 200) { log(`TZL Fonts: download of ${local} returned ${fr.status}`); return same ? 0 : 1; }
      await atomicWrite(dest, Buffer.from(await fr.arrayBuffer()));
      log(`  + ${local}`);
    }
    files.push(local);
    css = css.split(ref).join(`${urlPrefix}/${local}`);
  }

  await atomicWrite(cssFile, css);
  await atomicWrite(lockFile, JSON.stringify({ source: cssUrl, prefix: urlPrefix, etag, fetched: Date.now(), files }, null, 2));

  // remove files that the previous run created but are no longer referenced
  for (const old of (lock && lock.files) || []) {
    if (!files.includes(old)) { await rm(path.join(out, old), { force: true }); log(`  - ${old}`); }
  }
  log(`TZL Fonts: wrote ${path.relative(process.cwd(), cssFile) || cssFile} (${files.length} font file${files.length === 1 ? '' : 's'}). Link it with <link rel="stylesheet" href="${urlPrefix}/${o.name}.css">`);
  return 0;
}

// run only when executed directly
if (import.meta.url === new URL(process.argv[1] ? 'file://' + path.resolve(process.argv[1]) : 'file:///').href) {
  run(process.argv.slice(2)).then((c) => process.exit(c), (e) => { console.error(`TZL Fonts: ${e.message}`); process.exit(1); });
}
