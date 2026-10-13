<?php
use App\App;
use App\FontService;
use App\Store;
use App\View;

/** @var array $font */
/** @var array $fonts */
$base = App::baseUrl();
$fam = (string)$font['family'];
$plus = str_replace(' ', '+', $fam);
$styles = $font['styles'];
$hasItalic = false;
foreach ($styles as $s) { if (!empty($s['italic'])) { $hasItalic = true; } }

/** css2 "family" spec for a list of style indexes */
$spec = static function (array $idx) use ($styles, $plus, $hasItalic): string {
    $t = [];
    foreach ($idx as $i) {
        $s = $styles[$i];
        $w = !empty($s['variable']) ? (int)$s['wmin'] : (int)$s['weight'];
        $t[] = [!empty($s['italic']) ? 1 : 0, $w];
    }
    sort($t);
    $parts = [];
    foreach ($t as [$it, $w]) { $parts[] = $hasItalic ? $it . ',' . $w : (string)$w; }
    return $plus . ':' . ($hasItalic ? 'ital,wght@' : 'wght@') . implode(';', array_unique($parts));
};
$allIdx = array_keys($styles);
$defaultIdx = [0];
foreach ($styles as $i => $s) { if (empty($s['italic']) && ((int)$s['weight'] === 400 || !empty($s['variable']))) { $defaultIdx = [$i]; break; } }
$defSpec = $spec($defaultIdx);
$cssUrl = $base . '/css2?family=' . $defSpec . '&display=swap';

$meta = [];
foreach ($styles as $i => $s) {
    $meta[] = ['i' => $i, 'label' => FontService::styleLabel($s), 'w' => !empty($s['variable']) ? (int)$s['wmin'] : (int)$s['weight'],
        'wmax' => !empty($s['variable']) ? (int)$s['wmax'] : (int)$s['weight'], 'it' => !empty($s['italic']), 'v' => !empty($s['variable'])];
}
$glyphs = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!?@#$%&*()+=-_.,;:\'"/\\<>[]{}ÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÑÒÓÔÕÖØÙÚÛÜÝßàáâãäåæçèéêëìíîïñòóôõöøùúûüýÿ€£¥©®™';
$related = array_filter($fonts, static fn($x) => $x['slug'] !== $font['slug'] && $x['category'] === $font['category']);
$related = array_slice($related, 0, 3, true);
$licUrl = FontService::licenseUrl($font);
$cacheDays = (int)Store::setting('cache_days');

View::head($fam, $fam . ' — ' . ($font['description'] !== '' ? mb_substr((string)$font['description'], 0, 140) : 'open-source font, free for commercial use.'), ['nav' => 'fonts']);
?>
<link rel="stylesheet" href="/css2?family=<?= h($spec($allIdx)) ?>&amp;display=swap">
<main id="main-content" class="font-page" data-family="<?= h($fam) ?>" data-slug="<?= h($font['slug']) ?>" data-base="<?= h($base) ?>" data-plus="<?= h($plus) ?>"
      data-has-italic="<?= $hasItalic ? '1' : '0' ?>" data-styles="<?= h(json_encode($meta)) ?>" data-cache-days="<?= $cacheDays ?>">
  <nav class="breadcrumb" aria-label="Breadcrumb"><a href="/"><?= ico('home') ?> Home</a> <?= ico('chevron-right') ?> <a href="/#fonts">Fonts</a> <?= ico('chevron-right') ?> <span><?= h($fam) ?></span></nav>

  <header class="font-head">
    <div>
      <h1 class="font-title" data-font-title><?= h($fam) ?></h1>
      <p class="font-by">by <?= h($font['designer'] !== '' ? $font['designer'] : 'unknown designer') ?></p>
      <div class="badges">
        <span class="badge badge-accent"><?= ico('scale') ?> <?= h($font['license']) ?></span>
        <span class="badge"><?= h(Store::CATEGORIES[$font['category']] ?? $font['category']) ?></span>
        <?php if (!empty($font['variable'])): ?><span class="badge"><?= ico('sliders') ?> Variable</span><?php endif; ?>
        <span class="badge"><?= count($styles) ?> style<?= count($styles) === 1 ? '' : 's' ?></span>
        <?php foreach (($font['subsets'] ?? []) as $sb): ?><span class="badge badge-soft"><?= h($sb) ?></span><?php endforeach; ?>
      </div>
    </div>
    <div class="font-head-actions">
      <a class="btn btn-primary" href="#embed"><?= ico('code') ?> Get embed code</a>
      <a class="btn btn-secondary" href="/download/<?= h($font['slug']) ?>.zip"><?= ico('download') ?> Download family</a>
    </div>
  </header>

  <?php if ($font['description'] !== ''): ?><p class="font-desc"><?= h($font['description']) ?></p><?php endif; ?>

  <section class="card specimen" aria-labelledby="spec-h">
    <h2 id="spec-h"><?= ico('aa') ?> Specimen</h2>
    <div class="spec-controls">
      <label class="tool-field grow"><span class="sr-only">Preview text</span><input type="text" id="spec-text" value="Whereas recognition of the inherent dignity" maxlength="200"></label>
      <label class="tool-field size-field"><?= ico('text-size') ?><span class="sr-only">Size</span><input type="range" id="spec-size" min="12" max="140" value="56"><output id="spec-size-out">56px</output></label>
      <?php if (count($styles) > 1): ?>
      <label class="tool-field"><span class="sr-only">Style</span><select id="spec-style">
        <?php foreach ($meta as $m): ?><option value="<?= (int)$m['i'] ?>"<?= $m['i'] === $defaultIdx[0] ? ' selected' : '' ?>><?= h($m['label']) ?></option><?php endforeach; ?>
      </select></label>
      <?php endif; ?>
    </div>
    <div class="spec-preview" id="spec-preview" contenteditable="false" aria-live="polite">Whereas recognition of the inherent dignity</div>
  </section>

  <div class="font-cols">
    <div class="font-main">
      <section class="card" aria-labelledby="styles-h">
        <h2 id="styles-h"><?= ico('layers') ?> Styles</h2>
        <ul class="style-list">
          <?php foreach ($styles as $i => $s): ?>
          <li class="style-row" data-style="<?= (int)$i ?>">
            <div class="style-sample" data-w="<?= !empty($s['variable']) ? (int)$s['wmin'] : (int)$s['weight'] ?>" data-it="<?= !empty($s['italic']) ? '1' : '0' ?>">Almost before we knew it, we had left the ground.</div>
            <div class="style-meta"><span class="style-name"><?= h(FontService::styleLabel($s)) ?></span>
              <span class="style-files"><?php foreach ($s['files'] as $ext => $fi): ?>
                <a href="/f/<?= h($font['slug']) ?>/<?= h(rawurlencode((string)$fi['name'])) ?>?v=<?= h((string)($font['hash'] ?? '')) ?>" download title="<?= h(App::bytes((int)$fi['size'])) ?>"><?= ico('file-font') ?> <?= h(strtoupper((string)$ext)) ?></a>
              <?php endforeach; ?></span></div>
          </li>
          <?php endforeach; ?>
        </ul>
      </section>

      <section class="card" id="embed" aria-labelledby="embed-h">
        <h2 id="embed-h"><?= ico('code') ?> Use this font</h2>
        <?php if (count($styles) > 1): ?>
        <fieldset class="style-pick"><legend>Select the styles you need <small>(fewer styles = faster pages)</small></legend>
          <?php foreach ($meta as $m): ?>
          <label class="check-pill"><input type="checkbox" name="embed-style" value="<?= (int)$m['i'] ?>"<?= in_array($m['i'], $defaultIdx, true) ? ' checked' : '' ?>><span><?= h($m['label']) ?></span></label>
          <?php endforeach; ?>
        </fieldset>
        <?php endif; ?>

        <div class="tabs" data-tabs>
          <div class="tab-list" role="tablist" aria-label="Embed method">
            <button type="button" role="tab" aria-selected="true" data-tab="link"><?= ico('link') ?> &lt;link&gt;</button>
            <button type="button" role="tab" aria-selected="false" data-tab="import">@import</button>
            <button type="button" role="tab" aria-selected="false" data-tab="php"><?= ico('database-refresh') ?> PHP cache</button>
            <button type="button" role="tab" aria-selected="false" data-tab="node"><?= ico('terminal') ?> Node cache</button>
            <button type="button" role="tab" aria-selected="false" data-tab="nginx"><?= ico('server') ?> Nginx cache</button>
          </div>
          <div class="tab-panel" data-panel="link">
            <p class="hint">Paste into the <code>&lt;head&gt;</code> of your page.</p>
            <div class="code-block" data-lang="html"><pre><code data-out="link"><?= h('<link rel="stylesheet" href="' . $cssUrl . '">') ?></code></pre></div>
            <p class="hint">Then in your CSS:</p>
            <div class="code-block" data-lang="css"><pre><code data-out="css"><?= h("font-family: '" . $fam . "', system-ui, sans-serif;") ?></code></pre></div>
          </div>
          <div class="tab-panel" data-panel="import" hidden>
            <p class="hint">Inside a stylesheet. A <code>&lt;link&gt;</code> tag is faster when you can use one.</p>
            <div class="code-block" data-lang="css"><pre><code data-out="import"><?= h("@import url('" . $cssUrl . "');") ?></code></pre></div>
          </div>
          <div class="tab-panel" data-panel="php" hidden>
            <p class="hint">Downloads once, serves from your server, re-checks every <?= $cacheDays ?> days. <a href="/docs#method-a-php-helper-one-file">Full guide</a></p>
            <div class="code-block" data-lang="bash"><pre><code><?= h('curl -O ' . $base . '/download/tools/tzl-fonts.php') ?></code></pre></div>
            <div class="code-block" data-lang="php"><pre><code data-out="php"><?= h("<?= tzl_fonts('" . str_replace('+', ' ', $defSpec) . "') ?>") ?></code></pre></div>
          </div>
          <div class="tab-panel" data-panel="node" hidden>
            <p class="hint">Run in a build step or cron. <a href="/docs#method-b-node-script-build-step-or-cron">Full guide</a></p>
            <div class="code-block" data-lang="bash"><pre><code><?= h('curl -O ' . $base . '/download/tools/tzl-fonts.mjs') ?></code></pre></div>
            <div class="code-block" data-lang="bash"><pre><code data-out="node"><?= h('node tzl-fonts.mjs "' . str_replace('+', ' ', $defSpec) . '" --out public/fonts --url /fonts') ?></code></pre></div>
          </div>
          <div class="tab-panel" data-panel="nginx" hidden>
            <p class="hint">After adding the proxy-cache block from <a href="/docs#method-c-nginx-proxy-cache-no-code">the docs</a>:</p>
            <div class="code-block" data-lang="html"><pre><code data-out="nginx"><?= h('<link rel="stylesheet" href="/tzl-fonts/css2?family=' . $defSpec . '&display=swap">') ?></code></pre></div>
          </div>
        </div>
      </section>

      <section class="card" aria-labelledby="glyph-h">
        <h2 id="glyph-h"><?= ico('grid') ?> Characters</h2>
        <div class="glyph-grid" aria-hidden="true">
          <?php foreach (mb_str_split($glyphs) as $g): ?><span><?= h($g) ?></span><?php endforeach; ?>
        </div>
      </section>
    </div>

    <aside class="font-side">
      <section class="card info-card" aria-labelledby="info-h">
        <h2 id="info-h"><?= ico('info') ?> Details</h2>
        <dl class="info-list">
          <dt>Designer</dt><dd><?= h($font['designer'] !== '' ? $font['designer'] : '—') ?></dd>
          <dt>Category</dt><dd><?= h(Store::CATEGORIES[$font['category']] ?? $font['category']) ?></dd>
          <?php if (!empty($font['font_version'])): ?><dt>Version</dt><dd><?= h($font['font_version']) ?></dd><?php endif; ?>
          <?php if (!empty($font['glyphs'])): ?><dt>Glyphs</dt><dd><?= (int)$font['glyphs'] ?></dd><?php endif; ?>
          <dt>Added</dt><dd><?= h(gmdate('j M Y', strtotime((string)$font['added']))) ?></dd>
          <dt>Updated</dt><dd><?= h(gmdate('j M Y', strtotime((string)$font['updated']))) ?></dd>
          <?php if (!empty($font['source_url'])): ?><dt>Source</dt><dd><a href="<?= h($font['source_url']) ?>" target="_blank" rel="noopener"><?= ico('external-link') ?> Project page</a></dd><?php endif; ?>
        </dl>
        <?php if (!empty($font['tags'])): ?><div class="badges"><?php foreach ($font['tags'] as $t): ?><span class="badge badge-soft"><?= ico('tag') ?> <?= h($t) ?></span><?php endforeach; ?></div><?php endif; ?>
      </section>

      <section class="card info-card" aria-labelledby="lic-h">
        <h2 id="lic-h"><?= ico('scale') ?> License</h2>
        <p><strong><?= h(FontService::licenseName($font)) ?></strong><br>Free for personal and commercial use.</p>
        <?php if (!empty($font['copyright'])): ?><p class="hint"><?= h($font['copyright']) ?></p><?php endif; ?>
        <ul class="plain-links">
          <?php if ($licUrl !== ''): ?><li><a href="<?= h($licUrl) ?>" target="_blank" rel="noopener"><?= ico('external-link') ?> License details</a></li><?php endif; ?>
          <?php if (FontService::licenseText($font) !== ''): ?><li><a href="/f/<?= h($font['slug']) ?>/LICENSE.txt" target="_blank" rel="noopener"><?= ico('file-text') ?> LICENSE.txt</a></li><?php endif; ?>
          <li><a href="/license-policy"><?= ico('book-open') ?> Font License Policy</a></li>
        </ul>
      </section>

      <?php if ($related): ?>
      <section class="card info-card" aria-labelledby="rel-h">
        <h2 id="rel-h"><?= ico('sparkles') ?> Similar</h2>
        <ul class="plain-links"><?php foreach ($related as $r): ?><li><a href="/font/<?= h($r['slug']) ?>"><?= h($r['family']) ?></a></li><?php endforeach; ?></ul>
      </section>
      <?php endif; ?>
    </aside>
  </div>
</main>
<?php View::foot(); ?>
