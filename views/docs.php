<?php
use App\App;
use App\Docs;
use App\Markdown;
use App\View;

$md = Docs::markdown();
$toc = [];
$html = Markdown::render($md, $toc);
// the first H1 is rendered in the page header instead
$html = preg_replace('#^<h1 id="[^"]*">.*?</h1>\s*#s', '', $html, 1);
$intro = '';
if (preg_match('#^<blockquote>(.*?)</blockquote>\s*#s', $html, $m)) { $intro = $m[1]; $html = substr($html, strlen($m[0])); }

View::head('Documentation', 'How to use TechZeeLand Fonts: the CSS API, caching fonts on your own server, JSON API, licensing and more.', ['nav' => 'docs']);
?>
<main id="main-content" class="docs-wrap">
  <header class="docs-hero">
    <p class="eyebrow"><?= ico('book-open') ?> Documentation</p>
    <h1>How to use <?= h(View::site()) ?></h1>
    <div class="docs-intro"><?= $intro ?></div>
    <div class="docs-actions">
      <a class="btn btn-primary" href="/docs.md?download=1" download="tzl-fonts-docs.md"><?= ico('download') ?> Download docs as Markdown</a>
      <a class="btn btn-secondary" href="/docs.md" target="_blank" rel="noopener"><?= ico('file-text') ?> View raw Markdown</a>
      <button type="button" class="btn btn-secondary" data-copy-url><?= ico('link') ?> Copy link</button>
    </div>
  </header>

  <div class="docs-layout">
    <nav class="docs-toc" aria-label="On this page">
      <button type="button" class="docs-toc-toggle" aria-expanded="false"><?= ico('list') ?> On this page <?= ico('chevron-down', 'caret') ?></button>
      <ul>
        <?php foreach ($toc as $t): ?>
          <li class="lvl<?= (int)$t['level'] ?>"><a href="#<?= h($t['id']) ?>"><?= h($t['text']) ?></a></li>
        <?php endforeach; ?>
      </ul>
    </nav>
    <article class="docs-body prose"><?= $html ?></article>
  </div>
</main>
<?php View::foot(); ?>
