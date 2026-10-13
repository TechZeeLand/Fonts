<?php
use App\Admin;
use App\App;
use App\Auth;
use App\FontKit;
use App\FontService;
use App\Store;
use App\View;
/** @var array $font @var bool $isNew @var array $errors @var string $licenseText */
$caps = $caps ?? FontKit::caps();
$title = $isNew ? 'Add font' : 'Edit ' . $font['family'];
View::adminOpen($title, $isNew ? 'new' : 'fonts');
$action = $isNew ? '/admin/fonts/new' : '/admin/fonts/' . $font['slug'];
?>
<div class="page-head">
  <h1><?= $isNew ? 'Add a font' : h($font['family']) ?></h1>
  <?php if (!$isNew): ?><a class="btn btn-secondary" href="/font/<?= h($font['slug']) ?>" target="_blank" rel="noopener"><?= ico('external-link') ?> View on site</a><?php endif; ?>
</div>

<?php foreach ($errors as $e): ?><div class="alert alert-err" role="alert"><?= ico('alert-triangle') ?><div><?= h($e) ?></div></div><?php endforeach; ?>
<?php if (!($caps['fonttools'] && $caps['brotli'])): ?>
<div class="alert alert-info"><?= ico('info') ?><div>Automatic WOFF2 conversion is unavailable on this server (needs python3 + fonttools + brotli; the Docker image includes them). Upload <strong>.woff2</strong> files yourself, or TTF/OTF files will be served as they are.</div></div>
<?php endif; ?>

<form method="post" action="<?= h($action) ?>" enctype="multipart/form-data" class="admin-form" id="font-form">
  <?= Auth::field() ?>
  <button type="submit" name="do" value="save" class="sr-only" tabindex="-1" aria-hidden="true">Save</button>

  <section class="card">
    <h2><?= ico('font') ?> Basics</h2>
    <div class="field-row">
      <div class="field"><label for="family">Family name <span class="req">*</span></label>
        <input type="text" id="family" name="family" value="<?= h($font['family']) ?>" maxlength="80" required placeholder="e.g. Alata">
        <p class="hint"><?= $isNew ? 'The web address will be <code>/font/<span id="slug-preview">…</span></code>. It cannot be changed later.' : 'Slug: <code>' . h($font['slug']) . '</code> (fixed).' ?></p></div>
      <div class="field"><label for="designer">Designer(s)</label><input type="text" id="designer" name="designer" value="<?= h($font['designer']) ?>" maxlength="160" placeholder="Filled from the font file if blank"></div>
    </div>
    <div class="field-row">
      <div class="field"><label for="category">Category</label><select id="category" name="category">
        <?php foreach (Store::CATEGORIES as $k => $l): ?><option value="<?= h($k) ?>"<?= $font['category'] === $k ? ' selected' : '' ?>><?= h($l) ?></option><?php endforeach; ?>
      </select></div>
      <div class="field"><label for="tags">Tags</label><input type="text" id="tags" name="tags" value="<?= h(implode(', ', $font['tags'] ?? [])) ?>" maxlength="300" placeholder="geometric, friendly, headings"><p class="hint">Comma separated. Used for search.</p></div>
    </div>
    <div class="field"><label for="description">Description</label><textarea id="description" name="description" rows="3" maxlength="600" placeholder="A sentence or two shown on the font page."><?= h($font['description']) ?></textarea></div>
    <fieldset class="field"><legend>Language support <small>(detected automatically from uploads, adjust if needed)</small></legend>
      <div class="pill-grid"><?php foreach (Admin::SUBSETS as $sb): ?>
        <label class="check-pill"><input type="checkbox" name="subsets[]" value="<?= h($sb) ?>"<?= in_array($sb, $font['subsets'] ?? [], true) ? ' checked' : '' ?>><span><?= h($sb) ?></span></label>
      <?php endforeach; ?></div></fieldset>
  </section>

  <section class="card">
    <h2><?= ico('scale') ?> License &amp; source</h2>
    <p class="muted">Only fonts that are free for commercial use and may be redistributed can be hosted. See the <a href="/license-policy" target="_blank" rel="noopener">Font License Policy</a>.</p>
    <div class="field-row">
      <div class="field"><label for="license">License <span class="req">*</span></label><select id="license" name="license" required>
        <?php foreach (Store::LICENSES as $k => [$name]): ?><option value="<?= h($k) ?>"<?= ($font['license'] ?? '') === $k ? ' selected' : '' ?>><?= h($k) ?> — <?= h($name) ?></option><?php endforeach; ?>
      </select></div>
      <div class="field"><label for="license_url">License URL</label><input type="url" id="license_url" name="license_url" value="<?= h($font['license_url']) ?>" placeholder="Defaults to the standard URL"></div>
    </div>
    <div class="field-row">
      <div class="field"><label for="copyright">Copyright notice</label><input type="text" id="copyright" name="copyright" value="<?= h($font['copyright']) ?>" maxlength="300" placeholder="Copyright 2019 The Example Project Authors"></div>
      <div class="field"><label for="source_url">Source URL</label><input type="url" id="source_url" name="source_url" value="<?= h($font['source_url']) ?>" placeholder="https://github.com/…"></div>
    </div>
    <div class="field"><label for="license_text">License text <small>(shipped in the ZIP and at <code>/f/&lt;slug&gt;/LICENSE.txt</code>)</small></label>
      <textarea id="license_text" name="license_text" rows="6" class="mono" placeholder="Leave empty for SIL OFL 1.1 — the standard text is generated from the copyright notice. Paste the license for other licenses."><?= h($licenseText) ?></textarea></div>
  </section>

  <section class="card">
    <h2><?= ico('file-up') ?> Font files</h2>
    <?php if (!$isNew): ?>
    <div class="table-wrap">
    <table class="data-table styles-table">
      <thead><tr><th>Style</th><th>Weight</th><th>Italic</th><th>Name</th><th>Files</th><th class="right"></th></tr></thead>
      <tbody>
      <?php foreach ($font['styles'] as $i => $s): $var = !empty($s['variable']); ?>
        <tr>
          <td><?= h(FontService::styleLabel($s)) ?></td>
          <td><?php if ($var): ?><span class="muted"><?= (int)$s['wmin'] ?>–<?= (int)$s['wmax'] ?></span><?php else: ?><input type="number" min="1" max="1000" name="st_weight[<?= $i ?>]" value="<?= (int)$s['weight'] ?>" class="narrow" aria-label="Weight"><?php endif; ?></td>
          <td><?php if ($var): ?><?= !empty($s['italic']) ? 'Yes' : 'No' ?><?php else: ?><input type="checkbox" name="st_italic[<?= $i ?>]" value="1"<?= !empty($s['italic']) ? ' checked' : '' ?> aria-label="Italic"><?php endif; ?></td>
          <td><input type="text" name="st_name[<?= $i ?>]" value="<?= h($s['name'] ?? '') ?>" maxlength="40" aria-label="Style name"></td>
          <td class="file-chips">
            <?php foreach ($s['files'] as $ext => $fi): ?>
              <span class="file-chip" title="<?= h($fi['name']) ?> · <?= h(App::bytes((int)$fi['size'])) ?>"><?= ico('file-font') ?> <?= h(strtoupper((string)$ext)) ?> <small><?= h(App::bytes((int)$fi['size'])) ?></small>
                <button type="submit" name="do" value="delete_file:<?= $i ?>:<?= h((string)$ext) ?>" class="chip-x" data-confirm="Delete this <?= h(strtoupper((string)$ext)) ?> file?" aria-label="Delete <?= h(strtoupper((string)$ext)) ?> file"><?= ico('close') ?></button></span>
            <?php endforeach; ?>
            <?php if (!isset($s['files']['woff2'])): ?><button type="submit" name="do" value="regen:<?= $i ?>" class="btn btn-secondary btn-sm"><?= ico('refresh') ?> Make WOFF2</button><?php endif; ?>
          </td>
          <td class="right"><button type="submit" name="do" value="delete_style:<?= $i ?>" class="icon-btn danger" data-confirm="Delete this style and all of its files?" title="Delete style"><?= ico('trash') ?></button></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
    <?php endif; ?>

    <label class="dropzone" id="dropzone">
      <input type="file" name="files[]" id="files" multiple accept=".ttf,.otf,.woff,.woff2">
      <span class="dz-ico"><?= ico('cloud-upload', 'zi-2x') ?></span>
      <strong><?= $isNew ? 'Choose or drop font files' : 'Add more files' ?></strong>
      <span class="muted">TTF, OTF, WOFF or WOFF2 — up to <?= (int)Store::setting('max_upload_mb') ?> MB each. Weight, italic, variable axes and languages are detected automatically<?= ($caps['fonttools'] && $caps['brotli']) ? '; WOFF2 is generated for you' : '' ?>.</span>
      <span class="dz-list" id="dz-list"></span>
    </label>
  </section>

  <section class="card">
    <h2><?= ico('eye') ?> Visibility</h2>
    <div class="switch-list">
      <label class="row-check"><input type="checkbox" name="published" value="1"<?= !empty($font['published']) ? ' checked' : '' ?>><span class="switch"></span><span class="row-check-label">Published <small>— visible on the site and served by the CSS API</small></span></label>
      <label class="row-check"><input type="checkbox" name="featured" value="1"<?= !empty($font['featured']) ? ' checked' : '' ?>><span class="switch"></span><span class="row-check-label">Featured <small>— shows a badge in the catalog</small></span></label>
    </div>
  </section>

  <div class="form-actions">
    <button type="submit" name="do" value="save" class="btn btn-primary"><?= ico('save') ?> <?= $isNew ? 'Add font' : 'Save changes' ?></button>
    <a class="btn btn-secondary" href="/admin/fonts">Cancel</a>
  </div>

  <?php if (!$isNew): ?>
  <section class="card danger-zone">
    <h2><?= ico('alert-triangle') ?> Danger zone</h2>
    <p class="muted">Deleting a font removes its files for good and makes <code>/css2?family=<?= h(str_replace(' ', '+', (string)$font['family'])) ?></code> return 404 for everyone who uses it. Consider unpublishing instead.</p>
    <div class="inline-form">
      <input type="text" name="confirm_slug" placeholder="Type <?= h($font['slug']) ?> to confirm" autocomplete="off" aria-label="Type the slug to confirm deletion">
      <button type="submit" name="do" value="delete_font" class="btn btn-danger" data-confirm="Delete this font permanently?"><?= ico('trash') ?> Delete font</button>
    </div>
  </section>
  <?php endif; ?>
</form>
<?php View::adminClose(); ?>
