# Contributing

Thanks for helping! Bug reports, features, translations and fonts are all welcome.

- **Fonts:** open an issue with the font name, designer, source link and license. Only licenses that allow free commercial use and redistribution are accepted (see `/license-policy`).
- **Code:** plain PHP 8.1+, no framework, no database. Keep it dependency-free. Run `php -l` on files you change and `node --check` on JavaScript.
- **UI:** use the icon classes from icons.rayaz.org (`<i class="zi zi-name"></i>` via the `ico()` helper). New icons go in `new-icons/` as 24×24 stroke SVGs.
- **CSP:** the site sends `script-src 'self'; style-src 'self' <icon host>`. No inline scripts, inline `<style>` blocks or `style=""` attributes (set styles from JavaScript instead).
- **Docs:** edit `resources/docs.md`. Placeholders like `{{BASE_URL}}` and `{{FONT_TABLE}}` are filled live.

Open a pull request against `main` with a short description of what and why.
