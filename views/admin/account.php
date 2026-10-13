<?php
use App\Auth;
use App\View;
/** @var array $errors @var array|null $admin */
View::adminOpen('Account', 'account');
?>
<div class="page-head"><h1>Account</h1></div>
<?php foreach ($errors as $e): ?><div class="alert alert-err" role="alert"><?= ico('alert-triangle') ?><div><?= h($e) ?></div></div><?php endforeach; ?>
<form method="post" action="/admin/account" class="admin-form narrow-form" autocomplete="off">
  <?= Auth::field() ?>
  <section class="card"><h2><?= ico('user-lock') ?> Sign-in details</h2>
    <?php if (Auth::mustChange()): ?><div class="alert alert-info"><?= ico('info') ?><div>You are using a generated password. Please set your own now.</div></div><?php endif; ?>
    <div class="field"><label for="username">Username</label><input id="username" name="username" value="<?= h($admin['username'] ?? 'admin') ?>" autocomplete="username" required></div>
    <div class="field"><label for="current">Current password</label><input type="password" id="current" name="current" autocomplete="current-password" required></div>
    <div class="field"><label for="new_password">New password <small>(min. 10 characters<?= Auth::mustChange() ? '' : ', leave empty to keep the current one' ?>)</small></label><input type="password" id="new_password" name="new_password" autocomplete="new-password" minlength="10"<?= Auth::mustChange() ? ' required' : '' ?>></div>
    <div class="field"><label for="new_password2">Repeat new password</label><input type="password" id="new_password2" name="new_password2" autocomplete="new-password"></div>
    <button class="btn btn-primary" type="submit"><?= ico('save') ?> Update account</button>
  </section>
</form>
<?php View::adminClose(); ?>
