<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();

$u = auth_user();
$role = $u['role'] ?? 'mahasiswa';

$menuPath = __DIR__ . '/../data/menu.json';
$menuRaw = file_exists($menuPath) ? file_get_contents($menuPath) : '{}';
$menu = json_decode($menuRaw, true);
$items = $menu[$role] ?? [];
?>
<div class="overlay" id="overlay"></div>

<aside class="sidebar" id="sidebar">
  <div class="sidebar-head">
    <div class="sidebar-title">Menu</div>
    <button class="btn-icon" id="btnCloseSidebar" type="button" aria-label="Close sidebar">✕</button>
  </div>

  <nav class="sidebar-nav">
    <?php foreach ($items as $it): ?>
      <a class="sidebar-link" href="<?= e(url($it['path'])) ?>"><?= e($it['label']) ?></a>
    <?php endforeach; ?>
  </nav>
</aside>
