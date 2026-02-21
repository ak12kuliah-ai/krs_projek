<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_role('admin');

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';

$pdo = db();
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

// 1. IDENTIFIKASI TARGET
$targetId = (int)($_GET['id'] ?? 0);
if ($targetId <= 0) {
    flash_set('global', 'ID user tidak valid.', 'warning');
    redirect(url('admin/users/index.php'));
}

// Inisialisasi variabel untuk view
$targetUser = [];
$targetRole = '';
$mhs = ['npm' => '', 'prodi' => '', 'semester_aktif' => 1, 'angkatan' => '', 'no_hp' => '', 'dosen_wali_id' => 0];
$dsn = ['nidn' => '', 'prodi' => '', 'no_hp' => ''];
$dosenWaliOptions = [];

// 2. AMBIL DATA USER
try {
    $st = $pdo->prepare("SELECT id, name, email, role FROM users WHERE id = ? LIMIT 1");
    $st->execute([$targetId]);
    $targetUser = $st->fetch() ?: [];

    if (!$targetUser) {
        flash_set('global', 'User tidak ditemukan.', 'danger');
        redirect(url('admin/users/index.php'));
    }

    $targetRole = strtolower((string)($targetUser['role'] ?? ''));
    if (!in_array($targetRole, ['admin', 'dosen', 'mahasiswa'], true)) {
        flash_set('global', 'Role user tidak valid.', 'danger');
        redirect(url('admin/users/index.php'));
    }

    // Load data spesifik role
    if ($targetRole === 'mahasiswa') {
        $st = $pdo->prepare("SELECT npm, prodi, semester_aktif, angkatan, no_hp, dosen_wali_id FROM mahasiswa WHERE user_id = ? LIMIT 1");
        $st->execute([$targetId]);
        $mhs = array_merge($mhs, $st->fetch() ?: []);

        $dosenWaliOptions = $pdo->query("
            SELECT d.id AS dosen_id, u.name AS nama
            FROM dosen d
            JOIN users u ON u.id = d.user_id
            ORDER BY u.name
        ")->fetchAll() ?: [];
    } elseif ($targetRole === 'dosen') {
        $st = $pdo->prepare("SELECT nidn, prodi, no_hp FROM dosen WHERE user_id = ? LIMIT 1");
        $st->execute([$targetId]);
        $dsn = array_merge($dsn, $st->fetch() ?: []);
    }

} catch (Throwable $e) {
    flash_set('global', APP_DEBUG ? ('DB error: ' . $e->getMessage()) : 'Terjadi kesalahan.', 'danger');
    redirect(url('admin/users/index.php'));
}

// 3. PROSES SUBMIT UPDATE
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['_csrf'] ?? '')) {
        flash_set('global', 'CSRF tidak valid.', 'danger');
        redirect(url('admin/users/edit.php?id=' . $targetId));
    }

    if ((int)($_POST['id'] ?? 0) !== $targetId) {
        flash_set('global', 'ID target tidak cocok.', 'danger');
        redirect(url('admin/users/edit.php?id=' . $targetId));
    }

    $name  = trim((string)($_POST['name'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $pass  = (string)($_POST['password'] ?? '');
    $noHp  = trim((string)($_POST['no_hp'] ?? ''));

    if ($name === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash_set('global', 'Nama/Email wajib valid.', 'warning');
        redirect(url('admin/users/edit.php?id=' . $targetId));
    }

    try {
        $pdo->beginTransaction();

        // Update Tabel Users
        if ($pass !== '') {
            if (strlen($pass) < 6) {
                throw new Exception('Password minimal 6 karakter.');
            }
            $st = $pdo->prepare("UPDATE users SET name=?, email=?, password_hash=? WHERE id=?");
            $st->execute([$name, $email, password_hash($pass, PASSWORD_BCRYPT), $targetId]);
        } else {
            $st = $pdo->prepare("UPDATE users SET name=?, email=? WHERE id=?");
            $st->execute([$name, $email, $targetId]);
        }

        // Update Tabel Role-Spesifik
        if ($targetRole === 'mahasiswa') {
            $npm = trim((string)($_POST['npm'] ?? ''));
            $prodi = trim((string)($_POST['prodi'] ?? ''));
            $semesterAktif = max(1, min(8, (int)($_POST['semester_aktif'] ?? 1)));
            $angkatanRaw = trim((string)($_POST['angkatan'] ?? ''));

            if ($npm === '' || $prodi === '' || !ctype_digit($angkatanRaw)) {
                throw new Exception('Data mahasiswa (NPM/Prodi/Angkatan) tidak valid.');
            }

            $st = $pdo->prepare("UPDATE mahasiswa SET npm=?, prodi=?, semester_aktif=?, angkatan=?, no_hp=?, dosen_wali_id=? WHERE user_id=?");
            $st->execute([$npm, $prodi, $semesterAktif, (int)$angkatanRaw, ($noHp ?: null), ((int)$_POST['dosen_wali_id'] ?: null), $targetId]);

        } elseif ($targetRole === 'dosen') {
            $nidn = trim((string)($_POST['nidn'] ?? ''));
            if ($nidn === '') throw new Exception('Dosen wajib isi NIDN.');

            $st = $pdo->prepare("UPDATE dosen SET nidn=?, prodi=?, no_hp=? WHERE user_id=?");
            $st->execute([$nidn, (trim((string)$_POST['prodi']) ?: null), ($noHp ?: null), $targetId]);
        }

        $pdo->commit();
        flash_set('global', 'User berhasil diupdate.', 'success');
        
        $redirectMap = ['admin' => 'index_admin.php', 'dosen' => 'index_dosen.php', 'mahasiswa' => 'index_mahasiswa.php'];
        redirect(url('admin/users/' . ($redirectMap[$targetRole] ?? 'index.php')));

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        flash_set('global', APP_DEBUG ? $e->getMessage() : 'Gagal update user.', 'danger');
        redirect(url('admin/users/edit.php?id=' . $targetId));
    }
}
?>

<?php 
include __DIR__ . '/../../partials/header.php';
include __DIR__ . '/../../partials/navbar.php';
include __DIR__ . '/../../partials/flash.php';
?>

<div class="layout">
    <?php include __DIR__ . '/../../partials/sidebar.php'; ?>

    <main class="content">
        <div style="display:flex; justify-content:space-between; align-items:end; flex-wrap:wrap; gap:10px;">
            <div>
                <h2 style="margin:0;">Edit User</h2>
                <div style="color:#666; margin-top:6px;">
                    Role: <b><?= e($targetRole) ?></b> · ID: <b><?= (int)$targetId ?></b>
                </div>
            </div>
            <a class="btn" href="<?= e(url('admin/users/index.php')) ?>">Kembali</a>
        </div>

        <section style="background:#fff; border:1px solid #e6e6e6; border-radius:12px; padding:20px; max-width:980px; margin-top:12px;">
            <form method="post" action="<?= e(url('admin/users/edit.php?id='.$targetId)) ?>">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="id" value="<?= (int)$targetId ?>">

                <div style="display:grid; grid-template-columns: repeat(2, 1fr); gap:15px;">
                    <div>
                        <label style="display:block; margin-bottom:5px;">Nama*</label>
                        <input name="name" required value="<?= e((string)$targetUser['name']) ?>" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:10px;">
                    </div>

                    <div>
                        <label style="display:block; margin-bottom:5px;">Email*</label>
                        <input name="email" type="email" required value="<?= e((string)$targetUser['email']) ?>" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:10px;">
                    </div>

                    <div>
                        <label style="display:block; margin-bottom:5px;">Password (kosongkan jika tidak ganti)</label>
                        <input name="password" type="password" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:10px;">
                    </div>

                    <div>
                        <label style="display:block; margin-bottom:5px;">No HP (opsional)</label>
                        <input name="no_hp" value="<?= e((string)($mhs['no_hp'] ?: $dsn['no_hp'])) ?>" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:10px;">
                    </div>

                    <?php if ($targetRole === 'mahasiswa'): ?>
                        <div>
                            <label style="display:block; margin-bottom:5px;">NPM*</label>
                            <input name="npm" required value="<?= e($mhs['npm']) ?>" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:10px;">
                        </div>
                        <div>
                            <label style="display:block; margin-bottom:5px;">Prodi*</label>
                            <input name="prodi" required value="<?= e($mhs['prodi']) ?>" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:10px;">
                        </div>
                        <div>
                            <label style="display:block; margin-bottom:5px;">Semester Aktif (1-8)</label>
                            <input name="semester_aktif" type="number" min="1" max="8" value="<?= e((string)$mhs['semester_aktif']) ?>" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:10px;">
                        </div>
                        <div>
                            <label style="display:block; margin-bottom:5px;">Angkatan*</label>
                            <input name="angkatan" type="number" min="2000" max="2100" required value="<?= e((string)$mhs['angkatan']) ?>" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:10px;">
                        </div>
                        <div style="grid-column: 1 / -1;">
                            <label style="display:block; margin-bottom:5px;">Dosen Wali (opsional)</label>
                            <select name="dosen_wali_id" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:10px;">
                                <option value="0">- belum diset -</option>
                                <?php foreach ($dosenWaliOptions as $op): ?>
                                    <option value="<?= (int)$op['dosen_id'] ?>" <?= ((int)$mhs['dosen_wali_id'] === (int)$op['dosen_id']) ? 'selected' : '' ?>>
                                        <?= e($op['nama']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                    <?php elseif ($targetRole === 'dosen'): ?>
                        <div>
                            <label style="display:block; margin-bottom:5px;">NIDN*</label>
                            <input name="nidn" required value="<?= e($dsn['nidn']) ?>" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:10px;">
                        </div>
                        <div>
                            <label style="display:block; margin-bottom:5px;">Prodi (opsional)</label>
                            <input name="prodi" value="<?= e($dsn['prodi']) ?>" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:10px;">
                        </div>
                    <?php endif; ?>
                </div>

                <div style="display:flex; gap:10px; margin-top:20px;">
                    <button class="btn" type="submit">Update Data</button>
                    <a class="btn" href="<?= e(url('admin/users/index.php')) ?>" style="background:#eee; color:#333;">Batal</a>
                </div>
            </form>
        </section>
    </main>
</div>

<?php include __DIR__ . '/../../partials/footer.php'; ?>