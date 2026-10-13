# {{SITE_NAME}} documentation

> Open-source fonts you can pull into any project with one line of code, and cache on your own server so your website never waits on ours. Free for commercial use. No tracking, no accounts, no cookies.

This guide covers what {{SHORT_NAME}} is, why it exists, how to use every font in it, and how to cache the files on your own server so they load fast and keep working even if this service is offline. It was generated from {{BASE_URL}}/docs (content version {{VERSION}}).

## Why {{SHORT_NAME}} exists

Font CDNs are convenient, but they come with trade-offs: an extra DNS lookup and TLS connection to a third party on every first visit, a stylesheet that blocks rendering, visitor requests that leave your control, and a service you cannot inspect or run yourself.

{{SHORT_NAME}} keeps the convenience and removes those trade-offs:

- **Fully open source.** The software is AGPL-3.0 and every font ships under an open license that allows free commercial use. You can run your own copy of the whole service.
- **Cache-first by design.** The CSS and font files are served with long-lived cache headers, and the helper tools in this guide copy them to *your* server. Your visitors then load fonts from your domain, with no third-party request at all.
- **Self-refreshing.** Your cached copy re-checks this service every {{CACHE_DAYS}} days by default using a cheap conditional request, and only downloads again when a font really changed. If this service is down, your site keeps using what it already has.
- **Google-compatible API.** The CSS endpoint understands the same `family=Name:wght@400;700` syntax, so moving a project over is usually a one-line change.
- **Private.** No cookies, no analytics scripts, no IP addresses stored by the application.

## Quick start

Add this to the `<head>` of your page:

```html
<link rel="stylesheet" href="{{BASE_URL}}/css2?family={{EXAMPLE_PLUS}}&display=swap">
```

Then use the font in your CSS:

```css
body {
  font-family: '{{EXAMPLE}}', system-ui, sans-serif;
}
```

That is all you need to get started. For production sites, read [Cache fonts on your own server](#cache-fonts-on-your-own-server) next: it takes a few minutes and removes the third-party request completely.

## Available fonts

There are currently **{{FONT_COUNT}}** fonts available: {{FONT_NAMES}}.

{{FONT_TABLE}}

This list is generated live, so it grows automatically as new fonts are published. Machine-readable version: [{{BASE_URL}}/api/fonts.json]({{BASE_URL}}/api/fonts.json). Each font has its own page with a specimen, all styles, a license, a ZIP download and ready-to-copy embed code.

## The CSS API

The endpoint is `{{BASE_URL}}/css2`. It returns one `@font-face` block per requested style.

| Parameter | Example | What it does |
| --- | --- | --- |
| `family` | `family={{EXAMPLE_PLUS}}` | Font family. Use `+` for spaces. Repeat the parameter for more families. |
| `family` axes | `family=Name:wght@400;700` | Weights to include. Separate values with `;`. |
| `family` italics | `family=Name:ital,wght@0,400;1,700` | Pairs of `ital,wght` (0 normal, 1 italic). |
| `display` | `display=swap` | The `font-display` value: `auto`, `block`, `swap`, `fallback` or `optional`. Default: swap. |
| `legacy` | `legacy=1` | Also list WOFF and TTF fallbacks after WOFF2. Only needed for very old browsers. |

### Examples

One family, regular weight:

```html
<link rel="stylesheet" href="{{BASE_URL}}/css2?family={{EXAMPLE_PLUS}}&display=swap">
```

Regular and bold of the same family:

```html
<link rel="stylesheet" href="{{BASE_URL}}/css2?family={{EXAMPLE_PLUS}}:wght@400;700&display=swap">
```

Two families in one request:

```html
<link rel="stylesheet" href="{{BASE_URL}}/css2?family={{EXAMPLE_PLUS}}&family=Another+Font:ital,wght@0,400;1,400&display=swap">
```

Using `@import` inside a stylesheet (a `<link>` is faster because it does not wait for your CSS first):

```css
@import url('{{BASE_URL}}/css2?family={{EXAMPLE_PLUS}}&display=swap');
```

### Variable fonts

For a variable font, any weight inside its range returns the same single file, so `wght@400` and `wght@700` cost one download. In CSS you can then use any weight in the range, for example `font-weight: 550`.

### What the response looks like

```css
@font-face {
  font-family: '{{EXAMPLE}}';
  font-style: normal;
  font-weight: 400;
  font-display: swap;
  src: url({{BASE_URL}}/f/{{EXAMPLE_SLUG}}/{{EXAMPLE_FILE}}-Regular.woff2?v=abc123) format('woff2');
}
```

The `?v=` value is a content hash. It changes only when the font files change, which is why files can be cached for a year (`immutable`) and still update the moment a font is replaced.

### Errors

Unknown families or styles are skipped and mentioned in a comment at the end of the CSS. If nothing matches you get a `404` with an explanation; a request without `family` returns `400`.

## Using fonts in your CSS

Always provide a fallback stack, so text stays readable while the font loads or if it cannot load:

```css
:root {
  --font-body: '{{EXAMPLE}}', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
}
body { font-family: var(--font-body); }
```

Tips for fast pages:

- Request only the weights and styles you actually use. Every extra style is another file.
- Keep `display=swap` (the default) so text appears immediately in the fallback font.
- With a self-hosted cache you can preload your most important file: `<link rel="preload" href="/fonts/your-file.woff2" as="font" type="font/woff2" crossorigin>`. The `crossorigin` attribute is required for fonts, even on your own domain.
- Prefer a `<link>` tag over `@import`.

## Cache fonts on your own server

Loading fonts straight from {{HOST}} works, but the best setup is to copy them to your own server. Your visitors then make no third-party request, you control caching, and your site no longer depends on this service being online. Pick whichever method fits your stack.

| Method | Best for | Needs |
| --- | --- | --- |
| A. PHP helper | PHP sites, WordPress, Laravel | PHP 7.4+ with cURL or `allow_url_fopen` |
| B. Node script | Static sites, Next.js, Vite, Astro, any build step | Node 18+ |
| C. Nginx proxy cache | Any site behind Nginx, zero code | Nginx |
| D. Manual download | One-off projects | A ZIP from the font page |

### Method A: PHP helper (one file)

Download the helper, put it in your project, and call it where you print your `<head>`.

```bash
curl -O {{BASE_URL}}/download/tools/tzl-fonts.php
```

```php
<?php
require __DIR__ . '/tzl-fonts.php';
?>
<head>
  <?= tzl_fonts('{{EXAMPLE}}:wght@400;700') ?>
</head>
```

What happens: the first request downloads the CSS and font files into a cache folder next to your script (`tzl-fonts-cache/`), rewrites the URLs to point at your own domain, and inlines the CSS into your page. Later requests read straight from disk with no network call. After {{CACHE_DAYS}} days the helper re-checks {{HOST}} with a conditional request: if nothing changed it just resets the timer, otherwise it downloads the new files. If {{HOST}} cannot be reached, it keeps serving the old copy.

Options (second argument):

```php
echo tzl_fonts('{{EXAMPLE}}:wght@400;700', [
    'dir'     => __DIR__ . '/tzl-fonts-cache', // where files are stored (must be writable)
    'url'     => '/tzl-fonts-cache',           // public URL path of that folder
    'ttl'     => {{CACHE_DAYS}} * 86400,       // seconds before re-checking
    'display' => 'swap',
    'inline'  => true,                         // false = print a <link> to a cached .css file
    'preload' => true,                         // preload the first font file
]);
```

Pass several families as an array: `tzl_fonts(['{{EXAMPLE}}:wght@400;700', 'Another Font'])`.

To warm the cache without waiting for a visitor (for example from cron), run:

```bash
php tzl-fonts.php refresh "{{EXAMPLE}}:wght@400;700"
```

Make sure the cache folder is writable by your web server user and that your web server serves it as static files.

### Method B: Node script (build step or cron)

```bash
curl -O {{BASE_URL}}/download/tools/tzl-fonts.mjs
node tzl-fonts.mjs "{{EXAMPLE}}:wght@400;700" --out public/fonts --url /fonts
```

This writes `public/fonts/tzl-fonts.css`, the font files and a small lock file. Load it like any other stylesheet:

```html
<link rel="stylesheet" href="/fonts/tzl-fonts.css">
```

Run it before every build so the copy refreshes itself, for example in `package.json`:

```json
{
  "scripts": {
    "prebuild": "node tzl-fonts.mjs \"{{EXAMPLE}}:wght@400;700\" --out public/fonts --url /fonts"
  }
}
```

The script skips the network when the cache is newer than {{CACHE_DAYS}} days (change it with `--ttl 3d`, `--ttl 12h`), uses `If-None-Match` so unchanged fonts are not downloaded again, and never fails your build if {{HOST}} is unreachable while a cached copy exists. Use `--force` to refresh immediately. Several families: pass several quoted arguments.

### Method C: Nginx proxy cache (no code)

Let Nginx fetch, store and refresh everything for you. Add this to the `http` block:

```nginx
proxy_cache_path /var/cache/nginx/tzlfonts levels=1:2 keys_zone=tzlfonts:10m
                 max_size=500m inactive=60d use_temp_path=off;
```

And this to your `server` block:

```nginx
location /tzl-fonts/ {
    proxy_pass https://{{HOST}}/;
    proxy_ssl_server_name on;
    proxy_set_header Host {{HOST}};
    proxy_set_header Accept-Encoding "";

    proxy_cache tzlfonts;
    proxy_cache_valid 200 {{CACHE_DAYS}}d;
    proxy_cache_use_stale error timeout updating http_500 http_502 http_503 http_504;
    proxy_cache_background_update on;
    proxy_cache_lock on;

    # rewrite the absolute font URLs inside the CSS to your own path
    sub_filter '{{BASE_URL}}/' '/tzl-fonts/';
    sub_filter_once off;
    sub_filter_types text/css;

    add_header X-Cache-Status $upstream_cache_status;
}
```

Then use `<link rel="stylesheet" href="/tzl-fonts/css2?family={{EXAMPLE_PLUS}}&display=swap">`. Nginx serves repeat requests from disk, refreshes entries in the background after {{CACHE_DAYS}} days, and keeps serving stale files if {{HOST}} is down. Check the `X-Cache-Status` header: `HIT` means it came from your cache.

### Method D: Manual download

Open a font page, click **Download family**, and unzip. Upload the `.woff2` files to your site and declare them yourself:

```css
@font-face {
  font-family: '{{EXAMPLE}}';
  font-style: normal;
  font-weight: 400;
  font-display: swap;
  src: url('/fonts/{{EXAMPLE_FILE}}-Regular.woff2') format('woff2');
}
```

Keep the `LICENSE.txt` from the ZIP next to the files. Manual copies do not refresh by themselves, so re-download occasionally.

## How refreshing works

Fonts rarely change, so everything is built around cheap re-checks:

- **CSS responses** are cacheable for {{CSS_TTL_HOURS}} hours (`Cache-Control: public, max-age`), with `stale-while-revalidate` and `stale-if-error`, and an `ETag` so a re-check costs a tiny `304 Not Modified`.
- **Font files** are addressed by a content hash (`?v=...`) and cached for a year as `immutable`. A changed font gets a new hash, so there is never a stale file.
- **Your cache** (methods A to C) re-checks the CSS after {{CACHE_DAYS}} days. Unchanged: nothing is downloaded. Changed: only the new files are fetched and old ones are removed.
- **Manifest.** [{{BASE_URL}}/api/manifest.json]({{BASE_URL}}/api/manifest.json) lists every font and its hash in a few hundred bytes. Use it in your own scripts or monitoring if you want to know exactly when something changed.

## Recipes

### Plain HTML

Use the `<link>` from the quick start, or the files produced by method B.

### Tailwind CSS

Load the font (any method above), then extend your theme:

```js
// tailwind.config.js
module.exports = {
  theme: {
    extend: {
      fontFamily: { sans: ['"{{EXAMPLE}}"', 'system-ui', 'sans-serif'] },
    },
  },
};
```

### React, Next.js, Vite, Astro

Run method B in `prebuild`, then link the generated CSS once in your root layout or `index.html`. Because the files live in your own `public` folder, they are served by your host or CDN with your normal caching rules.

### WordPress

Place `tzl-fonts.php` in your theme folder, then in `functions.php`:

```php
require_once get_stylesheet_directory() . '/tzl-fonts.php';
add_action('wp_head', function () {
    echo tzl_fonts('{{EXAMPLE}}:wght@400;700', [
        'dir' => WP_CONTENT_DIR . '/uploads/tzl-fonts-cache',
        'url' => content_url('/uploads/tzl-fonts-cache'),
    ]);
}, 1);
```

### Moving from Google Fonts

Replace the host and keep everything else:

```html
<!-- before -->
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Name:wght@400;700&display=swap">
<!-- after -->
<link rel="stylesheet" href="{{BASE_URL}}/css2?family=Name:wght@400;700&display=swap">
```

Remove the `fonts.gstatic.com` preconnect line, as files come from the same host as the CSS. Differences to know about: the `text=` and `subset` parameters are not supported, and the response is WOFF2 unless you add `legacy=1`. Only fonts listed above are available.

## JSON API

All endpoints are read-only and send `Access-Control-Allow-Origin: *`.

| Endpoint | Returns |
| --- | --- |
| `GET /api/fonts.json` | Every published font with styles, license, and ready-made CSS and download URLs. |
| `GET /api/fonts/<slug>.json` | One font in detail, including each file with size, SHA-1 and URL. |
| `GET /api/manifest.json` | Version number plus a hash per font, for cheap update checks. |
| `GET /download/<slug>.zip` | All files of a font plus its license text. |
| `GET /f/<slug>/LICENSE.txt` | The license text of a font. |
| `GET /health` | Service status (for uptime monitors). |

```bash
curl {{BASE_URL}}/api/fonts.json
```

## Licensing and commercial use

Every font here is released under an open license that allows commercial use and redistribution without payment: SIL Open Font License 1.1, Apache 2.0, Ubuntu Font License 1.0, MIT, CC0 or CC BY 4.0. You may use them in websites, apps, print, video and products you sell.

What you should do:

- Keep the font's license text with any font **files** you redistribute (the ZIP includes it).
- Do not sell the font files by themselves (OFL), and do not use a modified font under the original reserved name.
- Check the license shown on each font page for the exact terms. This guide is a summary, not legal advice.

Read the full [Font License Policy]({{BASE_URL}}/license-policy).

## Privacy and security

- No cookies are set for visitors and there are no analytics scripts.
- The application stores only anonymous counters (requests per day and per font). It does not store IP addresses.
- The server that hosts this service, or a proxy in front of it, may keep ordinary access logs. See the [Privacy Policy]({{BASE_URL}}/privacy-policy).
- Font responses allow cross-origin use (CORS) so they work on any site.

## Run your own {{SHORT_NAME}}

The whole service is open source: {{GITHUB}}

```bash
git clone {{GITHUB}}.git
cd TZL-Fonts
cp .env.example .env      # set the admin password and your public URL
docker compose up -d --build
```

Open `http://localhost:1027`, sign in at `/admin`, and add fonts by uploading TTF, OTF, WOFF or WOFF2 files. WOFF2 versions are created automatically. The README covers HTTPS, reverse proxies, backups and updates.

## Troubleshooting

**The font does not show up.** Open your browser's network panel and look for the font request. A `404` usually means the family name is spelled differently (check the list above). A CORS error means the file was copied to a host that does not allow cross-origin font loads: serve fonts from the same domain as your page.

**It still looks like the fallback font.** Make sure your `font-family` name matches the family name exactly, and that you requested the weight you are using. Asking for weight 700 when you only loaded 400 makes the browser fake a bold.

**Text flashes when the page loads.** That is `font-display: swap` doing its job. Preload your main font file from your own domain (see tips above) to shorten the swap.

**My cache folder stays empty.** The folder must be writable by the web server user, and the PHP helper needs cURL or `allow_url_fopen`. The helper falls back to loading directly from {{HOST}} if it cannot write, so your site keeps working while you fix it.

**I want a font that is not listed.** See [Contribute a font]({{BASE_URL}}/contribute).

## Get help

Questions, bugs and font requests: {{CONTACT}} or {{GITHUB}}/issues.
