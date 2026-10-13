<?php
use App\App;
use App\Store;
use App\View;
use App\FontService;

/** @var array $fonts */
$base = App::baseUrl();
$sample = $fonts ? reset($fonts) : null;
$fam = $sample ? (string)$sample['family'] : 'Alata';
$linkCode = '<link rel="stylesheet" href="' . $base . '/css2?family=' . str_replace(' ', '+', $fam) . '&display=swap">';
$cats = [];
foreach ($fonts as $f) { $cats[$f['category']] = ($cats[$f['category']] ?? 0) + 1; }

View::head('Open-source fonts, served fast', '', ['raw_title' => false, 'nav' => 'fonts', 'canonical' => '/']);
?>
<main id="main-content">
<section class="hero">
  <div class="hero-inner">
    <p class="eyebrow"><?= ico('leaf') ?> Free for commercial use &middot; Open source</p>
    <h1>Open-source fonts.<br><span class="grad">Served fast. Cached on your server.</span></h1>
    <p class="hero-lede">Pull any font into your project with one line of CSS, like Google Fonts. Then let our helper keep a copy on <em>your</em> server, so visitors never wait on a third party and your pages stay quick.</p>
    <div class="hero-actions">
      <a class="btn btn-primary" href="#fonts"><?= ico('font') ?> Browse fonts</a>
      <a class="btn btn-secondary" href="/docs"><?= ico('book-open') ?> Read the docs</a>
    </div>
    <div class="code-block hero-code" data-lang="html"><pre><code><?= h($linkCode) ?></code></pre></div>
    <ul class="hero-stats">
      <li><strong><?= count($fonts) ?></strong><span><?= count($fonts) === 1 ? 'font' : 'fonts' ?> available</span></li>
      <li><strong>0</strong><span>cookies &amp; trackers</span></li>
      <li><strong><?= (int)Store::setting('cache_days') ?> d</strong><span>default cache refresh</span></li>
    </ul>
  </div>
</section>

<section class="features" aria-label="Why TZL Fonts">
  <div class="features-inner">
    <article class="feature"><span class="feature-ico"><?= ico('database-refresh') ?></span><h3>Cache-first</h3><p>Drop-in PHP, Node and Nginx helpers copy the files to your server and refresh them on a schedule. No request to us on page load.</p></article>
    <article class="feature"><span class="feature-ico"><?= ico('scale') ?></span><h3>Truly free</h3><p>Only fonts with open licenses that allow commercial use are accepted. License text travels with every download.</p></article>
    <article class="feature"><span class="feature-ico"><?= ico('zap') ?></span><h3>Fast by default</h3><p>WOFF2, year-long immutable file caching, ETags and stale-if-error so repeat visits cost almost nothing.</p></article>
    <article class="feature"><span class="feature-ico"><?= ico('shield-check') ?></span><h3>Private</h3><p>No cookies, no analytics scripts, no IP addresses stored. Only anonymous request counters.</p></article>
    <article class="feature"><span class="feature-ico"><?= ico('code') ?></span><h3>Google-compatible</h3><p>Same <code>family=Name:wght@400;700</code> syntax. Moving a project over is usually a one-line change.</p></article>
    <article class="feature"><span class="feature-ico"><?= ico('git-branch') ?></span><h3>Open source</h3><p>The whole service is AGPL-3.0. Run your own copy with Docker in a couple of minutes.</p></article>
  </div>
</section>

<section class="catalog" id="fonts" aria-labelledby="fonts-h">
  <div class="catalog-inner">
    <div class="section-head">
      <h2 id="fonts-h">Fonts</h2>
      <p>Click a font for the full specimen, styles and copy-paste embed code.</p>
    </div>

    <div class="toolbar" role="search">
      <label class="search-box"><?= ico('search') ?><span class="sr-only">Search fonts</span>
        <input type="search" id="font-search" placeholder="Search fonts, designers, tags…" autocomplete="off"></label>
      <label class="tool-field"><span class="sr-only">Preview text</span>
        <input type="text" id="preview-text" placeholder="Type to preview" value="The quick brown fox jumps over the lazy dog" maxlength="120"></label>
      <label class="tool-field size-field"><?= ico('text-size') ?><span class="sr-only">Preview size</span>
        <input type="range" id="preview-size" min="16" max="72" value="32"><output id="preview-size-out">32px</output></label>
      <label class="tool-field"><span class="sr-only">Sort</span>
        <select id="font-sort"><option value="name">Sort: name</option><option value="newest">Sort: newest</option><option value="styles">Sort: most styles</option></select></label>
    </div>

    <div class="chips" id="cat-chips" role="group" aria-label="Category">
      <button type="button" class="chip is-active" data-cat="all">All <span><?= count($fonts) ?></span></button>
      <?php foreach (Store::CATEGORIES as $k => $label): if (empty($cats[$k])) { continue; } ?>
        <button type="button" class="chip" data-cat="<?= h($k) ?>"><?= h($label) ?> <span><?= (int)$cats[$k] ?></span></button>
      <?php endforeach; ?>
    </div>

    <?php if (!$fonts): ?>
      <p class="empty-state"><?= ico('font') ?> No fonts are published yet. Check back soon.</p>
    <?php else: ?>
    <div class="font-grid" id="font-grid">
      <?php foreach ($fonts as $f):
          $n = count($f['styles']);
          $q = str_replace(' ', '+', (string)$f['family']);
      ?>
      <a class="font-card" href="/font/<?= h($f['slug']) ?>"
         data-name="<?= h(strtolower((string)$f['family'])) ?>"
         data-search="<?= h(strtolower($f['family'] . ' ' . $f['designer'] . ' ' . implode(' ', $f['tags'] ?? []) . ' ' . $f['category'])) ?>"
         data-cat="<?= h($f['category']) ?>" data-added="<?= h((string)strtotime((string)($f['added'] ?? 'now'))) ?>" data-styles="<?= $n ?>"
         data-css="/css2?family=<?= h($q) ?>&amp;display=swap" data-family="<?= h($f['family']) ?>">
        <div class="font-card-top">
          <span class="font-card-name"><?= h($f['family']) ?></span>
          <?php if (!empty($f['featured'])): ?><span class="badge badge-accent"><?= ico('star-filled') ?> Featured</span><?php endif; ?>
        </div>
        <div class="font-sample" aria-hidden="true">The quick brown fox jumps over the lazy dog</div>
        <div class="font-card-meta">
          <span><?= h(\App\Store::CATEGORIES[$f['category']] ?? $f['category']) ?></span>
          <span><?= !empty($f['variable']) ? 'Variable' : $n . ' style' . ($n === 1 ? '' : 's') ?></span>
          <span><?= h($f['license']) ?></span>
        </div>
        <div class="font-card-by">by <?= h($f['designer'] !== '' ? $f['designer'] : 'Unknown designer') ?></div>
      </a>
      <?php endforeach; ?>
    </div>
    <p class="empty-state" id="no-results" hidden><?= ico('search') ?> No fonts match your search.</p>
    <?php endif; ?>
  </div>
</section>

<section class="steps" aria-labelledby="how-h">
  <div class="steps-inner">
    <div class="section-head"><h2 id="how-h">How it works</h2><p>Three steps from zero to self-hosted fonts.</p></div>
    <ol class="step-list">
      <li><span class="step-n">1</span><h3>Pick a font</h3><p>Choose the styles you need on its page. The embed code updates as you click.</p></li>
      <li><span class="step-n">2</span><h3>Add one line</h3><p>Paste the <code>&lt;link&gt;</code> tag and set <code>font-family</code>. It works immediately.</p></li>
      <li><span class="step-n">3</span><h3>Cache it</h3><p>Use the PHP, Node or Nginx helper so the files live on your server and refresh on their own.</p></li>
    </ol>
    <p class="center"><a class="btn btn-primary" href="/docs#cache-fonts-on-your-own-server"><?= ico('database-refresh') ?> Set up caching</a></p>
  </div>
</section>
</main>
<?php View::foot(); ?>
