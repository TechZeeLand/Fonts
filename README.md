# TechZeeLand Fonts (TZL Fonts)

Official repo: [TechZeeLand/TZL-Fonts](https://github.com/TechZeeLand/TZL-Fonts)

A free, open-source, self-hosted **font hosting service**, like Google Fonts, but fully open and built to be **cached on the consumer's own server**. Add one line of CSS to use a font, then let a tiny helper keep a copy on *your* server so your pages never wait on a third party. Only fonts that are free for commercial use are hosted. Plain PHP, no framework, no database, no tracking.

The first font in the collection is **[Alata](https://github.com/SorkinType/Alata)** (SIL OFL 1.1), which is also the typeface of the site itself.

## Features

- **Google-compatible CSS API:** `/css2?family=Alata:wght@400;700&display=swap`, multiple families, `ital,wght` axes, variable fonts, legacy `family=Name:400,700italic|Other` syntax
- **Cache-first delivery:** immutable year-long caching for font files (content-hash URLs), ETag + `stale-while-revalidate` + `stale-if-error` for CSS, CORS enabled, WOFF2 first
- **Cache on your own server, auto-refreshing:** drop-in **PHP helper**, **Node script** and an **Nginx proxy-cache** recipe, each re-checks the service every few days with a conditional request and keeps working if the service is down
- **Catalog website:** live search, category filter, preview text and size, sort; font pages with specimen, all styles, glyphs, license and ZIP download, plus **copy-paste embed code that updates as you pick styles**
- **Full documentation** at `/docs` (why, how, recipes, API, licensing, troubleshooting). The font list inside it updates automatically and the whole guide can be downloaded as one **Markdown file** (`/docs.md?download=1`) for sharing
- **Admin portal** at `/admin`: dashboard with 14-day charts and health checks, add/edit/delete fonts, drag-and-drop upload (TTF, OTF, WOFF, WOFF2), automatic WOFF2 conversion, automatic detection of weight/italic/variable axes/languages/designer/copyright, license text management, publish/feature toggles, settings, account, activity log, one-click backup
- **License gate:** only OFL, Apache-2.0, UFL, MIT, CC0, CC BY 4.0 or an explicitly reviewed "Other" license can be selected; license text ships in every ZIP
- **Private:** no cookies for visitors, no analytics, no IP addresses stored, strict Content-Security-Policy
- **Pages:** Home, Font, Docs, About, Contact, Contribute, Privacy Policy, Terms & Conditions, Cookie Policy, Font License Policy, 403/404/500; light/dark theme; responsive
- **Icons** from [icons.rayaz.org](https://icons.rayaz.org) (`<i class="zi zi-home"></i>`)

## Run it

```bash
cp .env.example .env              # optional: set TZL_BASE_URL and an admin password
docker compose up -d --build      # http://localhost:1027
docker compose logs tzl-fonts     # shows the generated admin password on first start
```

Sign in at `http://localhost:1027/admin`. You are asked to change the generated password right away.

Without Docker (PHP 8.1+ with `mbstring`, `zip`, optional `curl`; optional `python3` + `fonttools` + `brotli` for WOFF2 conversion):

```bash
php -d upload_max_filesize=32M -d post_max_size=48M -S localhost:8080 -t public public/index.php
```

Pre-built image (after pushing to GitHub, see `.github/workflows`): `ghcr.io/techzeeland/tzl-fonts:latest`. In Portainer: *Stacks → Add stack*, paste `docker-compose.yml` (swap `build: .` for the `image:`).

### Configuration

| Variable | Default | Purpose |
| --- | --- | --- |
| `TZL_BASE_URL` | auto-detected | Public URL used in generated CSS and docs (can also be set in Admin → Settings) |
| `TZL_ADMIN_USER` | `admin` | First admin username |
| `TZL_ADMIN_PASSWORD` | random | First admin password; if empty a random one is printed to the log once |
| `TZL_TRUST_PROXY` | `0` | Set `1` behind a reverse proxy that terminates HTTPS (trusts `X-Forwarded-*`) |
| `TZL_DATA_DIR` | `./data` | Where fonts, settings and stats live |
| `TZL_ICON_CSS` | `https://icons.rayaz.org/zi.css` | Icon stylesheet (also used to build the CSP) |

Everything else (site name, caching times, CORS origin, announcement bar, upload limit…) is in **Admin → Settings**.

### HTTPS and reverse proxy

Put the container behind Caddy, Nginx, Traefik or Cloudflare and set `TZL_TRUST_PROXY=1` and `TZL_BASE_URL=https://fonts.example.com`. Minimal Caddy:

```
fonts.example.com {
    reverse_proxy tzl-fonts:80
}
```

## Admin portal

| Page | What it does |
| --- | --- |
| Dashboard | Totals, 14-day request chart, top fonts, health checks (WOFF2 tooling, ZIP, HTTPS, public URL), rescan files, backup, clear stats |
| Fonts | Searchable list with status, size and license |
| Add font | Family name, license, files. Weight, italic, variable axes, languages, designer and copyright are read from the font |
| Edit font | Metadata, license text, per-style weight/italic/name, add or remove files, generate WOFF2, publish/feature, delete |
| Settings | Site, announcement bar, caching, CORS, upload limit, privacy |
| Account | Change username and password |
| Activity | Log of admin actions |

Uploading a `.ttf` or `.otf` automatically creates a `.woff2` next to it (Docker image includes the tools). Files are named `Family-Style.ext`; font URLs contain a content hash, so replacing a file updates everyone's cache correctly. Changing fonts bumps the **manifest version** (`/api/manifest.json`), which cached copies use to refresh.

## Using the service

```html
<link rel="stylesheet" href="https://your-fonts-host/css2?family=Alata&display=swap">
```

See `/docs` on your instance, or [`resources/docs.md`](resources/docs.md) for the source of the documentation.

### JSON API

| Endpoint | Returns |
| --- | --- |
| `/api/fonts.json` | All published fonts |
| `/api/fonts/<slug>.json` | One font with every file, size and SHA-1 |
| `/api/manifest.json` | Version + hash per font (cheap update check) |
| `/download/<slug>.zip` | Files + license text |
| `/download/tools/tzl-fonts.php`, `/download/tools/tzl-fonts.mjs` | Cache helpers, pre-filled with your URL |
| `/health` | Status for uptime monitors |

## Icons

The UI uses the icon service at **icons.rayaz.org**: one stylesheet (`<link rel="stylesheet" href="https://icons.rayaz.org/zi.css">`, set in one place: `App::ICON_CSS` in `src/App.php`, or the `TZL_ICON_CSS` variable) and `<i class="zi zi-name"></i>` markup.

Five icons are new. Upload the files from [`new-icons/`](new-icons) to your icon host, keeping the folders:

| File | Class | Used for |
| --- | --- | --- |
| `new-icons/ui/aa.svg` | `zi-aa` | Specimen section |
| `new-icons/ui/database-refresh.svg` | `zi-database-refresh` | Caching / auto-refresh |
| `new-icons/ui/file-font.svg` | `zi-file-font` | Font file downloads and chips |
| `new-icons/ui/font-plus.svg` | `zi-font-plus` | Add font |
| `new-icons/social/pathaopay.svg` | `zi-pathaopay` | Support link in the footer |

The icons are loaded as CSS masks, so the icon host must send `Access-Control-Allow-Origin: *` for `/i/*` (or at least allow this site's origin), and the Content-Security-Policy in `App::securityHeaders()` allows the icon origin automatically.

## Project layout

```
public/index.php          front controller (Apache FallbackResource / php -S)
public/assets/            css, js, fonts (Alata UI subset), logo, favicons
src/App.php               config, URLs, security headers, helpers
src/Store.php             JSON storage, settings, fonts, stats, activity log
src/FontKit.php           font inspection + WOFF2 (python fonttools, PHP fallback)
src/FontService.php       uploads, styles, license text, ZIP, seeding
src/Css.php               Google-compatible CSS API
src/Web.php, Admin.php    public and admin controllers
src/Auth.php              admin session, CSRF, login throttling
src/View.php, Markdown.php, Docs.php, Router.php
views/                    page templates (views/admin for the portal)
resources/docs.md         documentation source (placeholders filled live)
resources/tools/          tzl-fonts.php and tzl-fonts.mjs cache helpers
seed/alata/               the first font, imported on first run
bin/                      fontkit.py (inspect / WOFF2), seed.php (first-run setup)
new-icons/                SVGs to upload to icons.rayaz.org
```

Data (`data/`): `fonts.json`, `settings.json`, `stats.json`, `admin.json`, `activity.log`, `files/<slug>/`. Back this folder up (Admin → Dashboard → Download backup covers fonts, settings and stats, but not passwords).

## Security notes

- Admin: password hashes (bcrypt/argon via `password_hash`), session cookie scoped to `/admin` (HttpOnly, SameSite=Strict, Secure on HTTPS), CSRF token on every POST, 6 failed logins per 15 minutes per address, all output escaped
- Uploads: extension *and* magic-byte checked, size-limited, stored under generated names outside the web root
- Public endpoints are read-only; font paths are matched against the stored file list (no path traversal)
- Serve the site over HTTPS in production. Report vulnerabilities as described in [SECURITY.md](SECURITY.md)

## Adding fonts responsibly

Only add fonts you have the right to redistribute under a license that allows free commercial use. Keep the license text, credit the designer, and link the source. See `/license-policy`.

## License

Software: [GNU AGPL-3.0](LICENSE). If you run a modified version as a network service, offer its source to your users. Fonts keep their own licenses (shown on each font page and included in every download). Alata is © The Alata Project Authors, SIL Open Font License 1.1.

Made by [TechZeeLand](https://github.com/TechZeeLand) · azlanaziz@rayaz.org
