<?php
use App\Auth;
use App\View;
/** @var string $error */
/** @var bool $firstRun */
View::head('Admin sign in', '', ['admin' => true]);
?>
<main id="main-content" class="login-wrap">
  <form method="post" action="/admin/login" class="card login-card" autocomplete="on">
    <div class="login-ico"><?= ico('user-lock') ?></div>
    <h1>Admin sign in</h1>
    <p class="muted">Manage fonts, settings and statistics.</p>
    <?php if ($error): ?><div class="alert alert-err" role="alert"><?= ico('alert-triangle') ?><div><?= h($error) ?></div></div><?php endif; ?>
    <?php if ($firstRun): ?><div class="alert alert-info"><?= ico('info') ?><div><strong>First run.</strong> A password was generated. Find it in the container log (<code>docker compose logs</code>) or in <code>data/INITIAL_ADMIN_PASSWORD.txt</code>. You will be asked to change it after signing in.</div></div><?php endif; ?>
    <?= Auth::field() ?>
    <div class="field"><label for="username">Username</label><input type="text" id="username" name="username" autocomplete="username" required autofocus></div>
    <div class="field"><label for="password">Password</label><input type="password" id="password" name="password" autocomplete="current-password" required></div>
    <button class="btn btn-primary btn-block" type="submit"><?= ico('login') ?> Sign in</button>
  </form>
</main>
<?php View::foot(['admin' => true]); ?>
