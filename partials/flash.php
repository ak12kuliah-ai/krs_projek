<?php
require_once __DIR__ . '/../includes/functions.php';
$flash = flash_get('global');
if ($flash):
?>
<div class="flash flash-<?= e($flash['type']) ?>">
  <?= e($flash['message']) ?>
</div>
<?php endif; ?>
