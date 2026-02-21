<?php
require_once __DIR__ . '/../includes/auth.php';
$u = auth_user();
?>
<header class="navbar">
  <button class="btn-icon" id="btnSidebar" type="button" aria-label="Toggle sidebar">☰</button>
  <div class="navbar-title"><?= e(APP_NAME) ?></div>

  <div class="navbar-right">
    <?php if ($u): ?>
      <span class="user-pill"><?= e($u['name']) ?> (<?= e($u['role']) ?>)</span>
      <a class="btn" href="<?= e(url('logout.php')) ?>">Logout</a>
    <?php endif; ?>
  </div>
</header>
