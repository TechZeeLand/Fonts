<?php
use App\Auth;
use App\Css;
use App\View;
/** @var array $s @var array $errors */
View::adminOpen('Settings', 'settings');
$chk = static fn($k) => !empty($s[$k]) ? ' checked' : '';
?>
<div class="page-head"><h1>Settings</h1></div>
<?php foreach ($errors as $e): ?><div class="alert alert-err" role="alert"><?= ico('alert-triangle') ?><div><?= h($e) ?></div></div><?php endforeach; ?>
<form method="post" action="/admin/settings" class="admin-form">
  <?= Auth::field() ?>
  <section class="card"><h2><?= ico('globe') ?> Site</h2>
    <div class="field-row">
      <div class="field"><label for="site_name">Site name</label><input id="site_name" name="site_name" value="<?= h($s['site_name']) ?>" maxlength="60"></div>
      <div class="field"><label for="short_name">Short name</label><input id="short_name" name="short_name" value="<?= h($s['short_name']) ?>" maxlength="30"></div>
    </div>
    <div class="field"><label for="tagline">Tagline</label><input id="tagline" name="tagline" value="<?= h($s['tagline']) ?>" maxlength="160"></div>
    <div class="field"><label for="base_url">Public URL</label><input type="url" id="base_url" name="base_url" value="<?= h($s['base_url']) ?>" placeholder="https://fonts.example.com">
      <p class="hint">Used in generated CSS, embed code, sitemap and docs. Leave empty to detect it from the request (set it when running behind a proxy).</p></div>
    <div class="field-row">
      <div class="field"><label for="contact_email">Contact email</label><input type="email" id="contact_email" name="contact_email" value="<?= h($s['contact_email']) ?>"></div>
      <div class="field"><label for="github_url">GitHub repository URL</label><input type="url" id="github_url" name="github_url" value="<?= h($s['github_url']) ?>"></div>
    </div>
  </section>

  <section class="card"><h2><?= ico('megaphone') ?> Announcement bar</h2>
    <label class="row-check"><input type="checkbox" name="announcement_on" value="1"<?= $chk('announcement_on') ?>><span class="switch"></span><span class="row-check-label">Show announcement above the header</span></label>
    <div class="field-row mt">
      <div class="field"><label for="announcement_text">Text</label><input id="announcement_text" name="announcement_text" value="<?= h($s['announcement_text']) ?>" maxlength="200"></div>
      <div class="field"><label for="announcement_link">Link (optional)</label><input type="url" id="announcement_link" name="announcement_link" value="<?= h($s['announcement_link']) ?>"></div>
    </div>
  </section>

  <section class="card"><h2><?= ico('zap') ?> Caching &amp; delivery</h2>
    <div class="field-row">
      <div class="field"><label for="css_ttl">CSS cache time (seconds)</label><input type="number" id="css_ttl" name="css_ttl" min="60" max="31536000" value="<?= (int)$s['css_ttl'] ?>">
        <p class="hint">How long browsers and proxies reuse <code>/css2</code> responses. 86400 = 1 day.</p></div>
      <div class="field"><label for="cache_days">Recommended refresh interval (days)</label><input type="number" id="cache_days" name="cache_days" min="1" max="365" value="<?= (int)$s['cache_days'] ?>">
        <p class="hint">Shown in the docs and used as the default of the PHP/Node helpers.</p></div>
    </div>
    <div class="field-row">
      <div class="field"><label for="cors_origin">Allowed origin (CORS)</label><input id="cors_origin" name="cors_origin" value="<?= h($s['cors_origin']) ?>"><p class="hint"><code>*</code> lets every website use your fonts. Use a single origin to restrict it.</p></div>
      <div class="field"><label for="default_display">Default font-display</label><select id="default_display" name="default_display">
        <?php foreach (Css::DISPLAY as $d): ?><option<?= $s['default_display'] === $d ? ' selected' : '' ?>><?= h($d) ?></option><?php endforeach; ?></select></div>
    </div>
    <div class="field"><label for="max_upload_mb">Max upload size per file (MB)</label><input type="number" id="max_upload_mb" name="max_upload_mb" min="1" max="200" value="<?= (int)$s['max_upload_mb'] ?>" class="narrow">
      <p class="hint">Also limited by PHP's <code>upload_max_filesize</code> and <code>post_max_size</code> (see Dashboard).</p></div>
  </section>

  <section class="card"><h2><?= ico('sliders') ?> Privacy &amp; search engines</h2>
    <div class="switch-list">
      <label class="row-check"><input type="checkbox" name="stats_on" value="1"<?= $chk('stats_on') ?>><span class="switch"></span><span class="row-check-label">Count requests <small>— anonymous daily totals per font, no IP addresses</small></span></label>
      <label class="row-check"><input type="checkbox" name="robots_index" value="1"<?= $chk('robots_index') ?>><span class="switch"></span><span class="row-check-label">Allow search engines to index the site</span></label>
    </div>
  </section>

  <div class="form-actions"><button class="btn btn-primary" type="submit"><?= ico('save') ?> Save settings</button></div>
</form>
<?php View::adminClose(); ?>
