<?php
use App\App;
use App\Store;
use App\View;
/** @var array $fonts */
View::adminOpen('Fonts', 'fonts');
?>
<div class="page-head"><h1>Fonts <span class="count-pill"><?= count($fonts) ?></span></h1><a class="btn btn-primary" href="/admin/fonts/new"><?= ico('font-plus') ?> Add font</a></div>
<section class="card">
  <label class="search-box wide"><?= ico('search') ?><span class="sr-only">Filter fonts</span><input type="search" id="admin-filter" placeholder="Filter by name, designer or license…" autocomplete="off"></label>
  <div class="table-wrap">
  <table class="data-table" id="admin-fonts">
    <thead><tr><th>Font</th><th>Category</th><th>Styles</th><th>Size</th><th>License</th><th>Status</th><th>Updated</th><th class="right">Actions</th></tr></thead>
    <tbody>
    <?php foreach ($fonts as $f):
        $bytes = 0; foreach ($f['styles'] as $s) { foreach ($s['files'] as $fi) { $bytes += (int)$fi['size']; } }
    ?>
      <tr data-filter="<?= h(strtolower($f['family'] . ' ' . $f['designer'] . ' ' . $f['license'])) ?>">
        <td><a class="strong-link" href="/admin/fonts/<?= h($f['slug']) ?>"><?= h($f['family']) ?></a><?php if (!empty($f['featured'])): ?> <?= ico('star-filled', 'star') ?><?php endif; ?><small><?= h($f['designer']) ?></small></td>
        <td><?= h(Store::CATEGORIES[$f['category']] ?? $f['category']) ?></td>
        <td><?= count($f['styles']) ?><?= !empty($f['variable']) ? ' (variable)' : '' ?></td>
        <td><?= h(App::bytes($bytes)) ?></td>
        <td><?= h($f['license']) ?></td>
        <td><?= !empty($f['published']) ? '<span class="badge badge-ok">Published</span>' : '<span class="badge badge-warn">Draft</span>' ?></td>
        <td><?= h(App::ago((string)$f['updated'])) ?></td>
        <td class="right actions"><a class="icon-btn" href="/admin/fonts/<?= h($f['slug']) ?>" title="Edit"><?= ico('square-pen') ?></a> <a class="icon-btn" href="/font/<?= h($f['slug']) ?>" target="_blank" rel="noopener" title="View on site"><?= ico('external-link') ?></a></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$fonts): ?><tr><td colspan="8" class="muted center">No fonts yet. <a href="/admin/fonts/new">Add the first one</a>.</td></tr><?php endif; ?>
    </tbody>
  </table>
  </div>
</section>
<?php View::adminClose(); ?>
