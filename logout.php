<?php
require_once __DIR__ . '/includes/auth.php';
auth_logout();
flash_set('global', 'Berhasil logout.', 'success');
redirect(url('login.php'));
