<?php
require_once __DIR__ . '/includes/auth.php';

if (auth_check()) redirect(url('index.php'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = trim($_POST['email'] ?? '');
  $pass  = trim($_POST['password'] ?? '');
  $token = $_POST['_csrf'] ?? '';

  if (!csrf_verify($token)) {
    flash_set('global', 'CSRF tidak valid.', 'danger');
    redirect(url('login.php'));
  }

  if (auth_login($email, $pass)) {
    redirect(url('index.php'));
  }

  flash_set('global', 'Email / password salah.', 'danger');
  redirect(url('login.php'));
}
?>
<?php include __DIR__ . '/partials/header.php'; ?>
<?php include __DIR__ . '/partials/flash.php'; ?>

<div style="max-width:420px;margin:40px auto;padding:16px;background:#fff;border:1px solid #e6e6e6;border-radius:12px;">
  <h2 style="margin:0 0 12px;">Login</h2>
  <form method="post">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <div style="margin-bottom:10px;">
      <label>Email</label><br>
      <input name="email" type="email" required style="width:100%;padding:10px;border:1px solid #ddd;border-radius:10px;">
    </div>
    <div style="margin-bottom:10px;">
      <label>Password</label><br>
      <input name="password" type="password" required style="width:100%;padding:10px;border:1px solid #ddd;border-radius:10px;">
    </div>
    <button class="btn" type="submit" style="width:100%;">Masuk</button>
  </form>
</div>

<?php include __DIR__ . '/partials/footer.php'; ?>
