<?php
use App\App;
use App\Auth;
use App\Store;
use App\View;
/** @var array $days @var array $sum @var array $today @var array $top @var array $fonts @var int $pub @var array $totals @var array $checks @var array $recent @var array $ini */
View::adminOpen('Dashboard', 'dashboard');
$max = 1;
foreach ($days as $d) { $max = max($max, $d['css'] + $d['file']); }
$W = 600; $H = 150; $n = count($days); $bw = 26; $gap = ($W - $n * $bw) / ($n + 1);
?>
<div class="page-head"><h1>Dashboard</h1><a class="btn btn-primary" href="/admin/fonts/new"><?= ico('font-plus') ?> Add font</a></div>

<div class="stat-grid">
  <div class="stat"><span class="stat-ico"><?= ico('font') ?></span><div><strong><?= (int)$pub ?></strong><span>published of <?= count($fonts) ?> fonts</span></div></div>
  <div class="stat"><span class="stat-ico"><?= ico('hard-drive') ?></span><div><strong><?= h(App::bytes((int)$totals['bytes'])) ?></strong><span><?= (int)$totals['files'] ?> font files</span></div></div>
  <div class="stat"><span class="stat-ico"><?= ico('activity') ?></span><div><strong><?= number_format($today['css'] + $today['file']) ?></strong><span>CSS + file requests today</span></div></div>
  <div class="stat"><span class="stat-ico"><?= ico('database-refresh') ?></span><div><strong>v<?= (int)Store::version() ?></strong><span>manifest version</span></div></div>
</div>

<div class="admin-cols">
  <section class="card">
    <h2><?= ico('bar-chart') ?> Requests, last 14 days</h2>
    <div class="chart-wrap">
      <svg viewBox="0 0 <?= $W ?> <?= $H + 22 ?>" class="chart" role="img" aria-label="Requests per day">
        <?php $i = 0; foreach ($days as $date => $d):
            $x = $gap + $i * ($bw + $gap);
            $hc = round(($d['css'] / $max) * $H); $hf = round(($d['file'] / $max) * $H);
        ?>
          <rect class="bar-file" x="<?= round($x, 1) ?>" y="<?= $H - $hf ?>" width="<?= $bw ?>" height="<?= max(0, $hf) ?>" rx="3"><title><?= h($date) ?>: <?= (int)$d['file'] ?> font files</title></rect>
          <rect class="bar-css" x="<?= round($x, 1) ?>" y="<?= $H - $hf - $hc ?>" width="<?= $bw ?>" height="<?= max(0, $hc) ?>" rx="3"><title><?= h($date) ?>: <?= (int)$d['css'] ?> CSS requests</title></rect>
          <?php if ($i % 2 === 0): ?><text class="chart-label" x="<?= round($x + $bw / 2, 1) ?>" y="<?= $H + 16 ?>" text-anchor="middle"><?= h(substr($date, 5)) ?></text><?php endif; ?>
        <?php $i++; endforeach; ?>
      </svg>
    </div>
    <p class="legend"><span class="dot dot-css"></span> CSS requests (<?= number_format($sum['css']) ?>) <span class="dot dot-file"></span> Font files (<?= number_format($sum['file']) ?>) &middot; ZIP downloads <?= number_format($sum['zip']) ?> &middot; API <?= number_format($sum['api']) ?></p>
    <?php if (!Store::setting('stats_on')): ?><p class="hint">Statistics are switched off in Settings.</p><?php endif; ?>
  </section>

  <section class="card">
    <h2><?= ico('trophy') ?> Top fonts (30 days)</h2>
    <?php if (!$top): ?><p class="muted">No requests recorded yet.</p><?php else: ?>
    <ol class="rank"><?php foreach ($top as $slug => $c): $f = $fonts[$slug] ?? null; ?>
      <li><a href="/admin/fonts/<?= h($slug) ?>"><?= h($f['family'] ?? $slug) ?></a><span><?= number_format($c) ?></span></li>
    <?php endforeach; ?></ol>
    <?php endif; ?>
  </section>
</div>

<div class="admin-cols">
  <section class="card">
    <h2><?= ico('shield-check') ?> System health</h2>
    <ul class="checks">
      <?php foreach ($checks as [$label, $ok, $help]): ?>
      <li class="<?= $ok ? 'ok' : 'warn' ?>"><?= ico($ok ? 'check-circle' : 'alert-triangle') ?><div><strong><?= h($label) ?></strong><?php if (!$ok): ?><small><?= h($help) ?></small><?php endif; ?></div></li>
      <?php endforeach; ?>
    </ul>
    <p class="hint">PHP upload limits: <code>upload_max_filesize=<?= h($ini['upload']) ?></code>, <code>post_max_size=<?= h($ini['post']) ?></code>.</p>
  </section>

  <section class="card">
    <h2><?= ico('wrench') ?> Tools</h2>
    <form method="post" action="/admin/tools" class="tool-forms">
      <?= Auth::field() ?>
      <button class="btn btn-secondary" name="do" value="rescan" type="submit"><?= ico('refresh') ?> Rescan files &amp; bump manifest</button>
      <a class="btn btn-secondary" href="/admin/backup.zip"><?= ico('archive') ?> Download backup</a>
      <button class="btn btn-secondary" name="do" value="clear_stats" type="submit" data-confirm="Clear all request statistics?"><?= ico('trash') ?> Clear statistics</button>
    </form>
    <p class="hint">Rescan re-reads every file from disk and bumps the manifest version so cached copies pick up changes. Backups contain fonts, settings and statistics (never passwords).</p>
    <h3 class="mt"><?= ico('history') ?> Recent activity</h3>
    <ul class="activity-mini"><?php foreach ($recent as $r): ?><li><span><?= h(App::ago((string)$r['t'])) ?></span> <?= h($r['m']) ?></li><?php endforeach; if (!$recent): ?><li class="muted">Nothing yet.</li><?php endif; ?></ul>
  </section>
</div>
<?php View::adminClose(); ?>
