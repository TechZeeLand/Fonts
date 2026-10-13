<?php
use App\App;
use App\View;
/** @var array $rows */
View::adminOpen('Activity', 'activity');
?>
<div class="page-head"><h1>Activity</h1></div>
<section class="card">
  <?php if (!$rows): ?><p class="muted">No activity recorded yet.</p><?php else: ?>
  <div class="table-wrap"><table class="data-table"><thead><tr><th>When (UTC)</th><th>User</th><th>Action</th></tr></thead><tbody>
    <?php foreach ($rows as $r): ?><tr><td class="nowrap"><?= h(gmdate('Y-m-d H:i', (int)strtotime((string)$r['t']))) ?> <small><?= h(App::ago((string)$r['t'])) ?></small></td><td><?= h($r['u']) ?></td><td><?= h($r['m']) ?></td></tr><?php endforeach; ?>
  </tbody></table></div>
  <?php endif; ?>
</section>
<?php View::adminClose(); ?>
