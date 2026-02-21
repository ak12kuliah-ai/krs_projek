<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_role('admin');

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';

$pdo = db();

// default role (boleh kosong biar user pilih)
$role = strtolower(trim((string)($_GET['role'] ?? $_POST['role'] ?? '')));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $token = $_POST['_csrf'] ?? '';
  if (!csrf_verify($token)) {
    flash_set('global', 'CSRF tidak valid.', 'danger');
    redirect(url('admin/users/add.php'));
  }

  $role  = strtolower(trim((string)($_POST['role'] ?? '')));
  $name  = trim((string)($_POST['name'] ?? ''));
  $email = trim((string)($_POST['email'] ?? ''));
  $pass  = (string)($_POST['password'] ?? '');
  $noHp  = trim((string)($_POST['no_hp'] ?? ''));

  if (!in_array($role, ['admin','dosen','mahasiswa'], true)) {
    flash_set('global', 'Role wajib dipilih.', 'warning');
    redirect(url('admin/users/add.php'));
  }
  if ($name === '' || $email === '' || $pass === '') {
    flash_set('global', 'Nama, email, dan password wajib diisi.', 'warning');
    redirect(url('admin/users/add.php'));
  }
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    flash_set('global', 'Format email tidak valid.', 'warning');
    redirect(url('admin/users/add.php'));
  }
  if (strlen($pass) < 6) {
    flash_set('global', 'Password minimal 6 karakter.', 'warning');
    redirect(url('admin/users/add.php'));
  }

  // field khusus (pakai nama variabel template kamu)
  $npm = trim((string)($_POST['npm'] ?? ''));       // NIM/NPM
  $prodi = trim((string)($_POST['prodi'] ?? ''));
  $angkatan = (int)($_POST['angkatan'] ?? 0);
  $nidn = trim((string)($_POST['nidn'] ?? ''));

  try {
    $pdo->beginTransaction();

    // 1) insert users
    $hash = password_hash($pass, PASSWORD_BCRYPT);
    $st = $pdo->prepare("INSERT INTO users (name, email, role, password_hash) VALUES (?, ?, ?, ?)");
    $st->execute([$name, $email, $role, $hash]);
    $userId = (int)$pdo->lastInsertId();

    // 2) insert tabel spesifik
    if ($role === 'mahasiswa') {
      if ($npm === '' || $prodi === '') {
        $pdo->rollBack();
        flash_set('global', 'Mahasiswa wajib isi NIM/NPM dan Prodi.', 'warning');
        redirect(url('admin/users/add.php'));
      }
      if ($angkatan < 2000 || $angkatan > 2100) {
        $pdo->rollBack();
        flash_set('global', 'Mahasiswa wajib isi Angkatan (contoh: 2023).', 'warning');
        redirect(url('admin/users/add.php'));
      }

      // OPSI B: semester_aktif dihitung otomatis dari semester aktif sistem
      $active = get_active_semester($pdo);
      $semesterAktif = 1;
      if ($active) {
        $semesterAktif = hitung_semester_ke((int)$angkatan, (string)$active['nama'], (string)$active['periode']);
      }

      $hp = ($noHp === '') ? null : $noHp;

      $st = $pdo->prepare("
        INSERT INTO mahasiswa (user_id, npm, prodi, semester_aktif, angkatan, no_hp)
        VALUES (?, ?, ?, ?, ?, ?)
      ");
      $st->execute([$userId, $npm, $prodi, $semesterAktif, $angkatan, $hp]);

    } elseif ($role === 'dosen') {
      if ($nidn === '') {
        $pdo->rollBack();
        flash_set('global', 'Dosen wajib isi NIDN.', 'warning');
        redirect(url('admin/users/add.php'));
      }

      $prd = ($prodi === '') ? null : $prodi;
      $hp  = ($noHp === '') ? null : $noHp;

      $st = $pdo->prepare("INSERT INTO dosen (user_id, nidn, prodi, no_hp) VALUES (?, ?, ?, ?)");
      $st->execute([$userId, $nidn, $prd, $hp]);
    }

    $pdo->commit();

    flash_set('global', 'User berhasil ditambahkan.', 'success');
    $back = ($role === 'admin') ? 'index_admin.php' : (($role === 'dosen') ? 'index_dosen.php' : 'index_mahasiswa.php');
    redirect(url('admin/users/' . $back));
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    flash_set('global', APP_DEBUG ? ('DB error: '.$e->getMessage()) : 'Gagal menambah user (cek email/NPM/NIDN mungkin sudah dipakai).', 'danger');
    redirect(url('admin/users/add.php'));
  }
}
?>

<?php include __DIR__ . '/../../partials/header.php'; ?>
<?php include __DIR__ . '/../../partials/navbar.php'; ?>
<?php include __DIR__ . '/../../partials/flash.php'; ?>

<div class="layout">
  <?php include __DIR__ . '/../../partials/sidebar.php'; ?>
  <main class="content">
    <div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;align-items:end;">
      <div>
        <h2 style="margin:0;">Tambah Pengguna Baru</h2>
        <div style="color:#666;margin-top:6px;">Pilih role, lalu isi field yang muncul.</div>
      </div>
      <a class="btn" href="<?= e(url('admin/users/index.php')) ?>">Kembali</a>
    </div>

    <section style="background:#fff;border:1px solid #e6e6e6;border-radius:12px;padding:12px;max-width:980px;margin-top:12px;">
      <form method="post">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">

        <div style="margin-bottom:12px;">
          <label>Role*</label>
          <select name="role" id="roleSelect" onchange="toggleFields()" required
                  style="width:280px;padding:10px;border:1px solid #ddd;border-radius:10px;">
            <option value="">-- Pilih Role --</option>
            <option value="admin" <?= ($role==='admin')?'selected':''; ?>>Admin</option>
            <option value="dosen" <?= ($role==='dosen')?'selected':''; ?>>Dosen</option>
            <option value="mahasiswa" <?= ($role==='mahasiswa')?'selected':''; ?>>Mahasiswa</option>
          </select>
        </div>

        <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;">
          <div>
            <label>Nama*</label>
            <input name="name" required style="width:100%;padding:10px;border:1px solid #ddd;border-radius:10px;">
          </div>

          <div>
            <label>Email*</label>
            <input name="email" type="email" required style="width:100%;padding:10px;border:1px solid #ddd;border-radius:10px;">
          </div>

          <div>
            <label>Password*</label>
            <input name="password" type="password" required style="width:100%;padding:10px;border:1px solid #ddd;border-radius:10px;">
          </div>

          <div>
            <label>No HP (opsional)</label>
            <input name="no_hp" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:10px;">
          </div>

          <!-- DOSEN FIELD -->
          <div id="dosenField" style="display:none;">
            <label>NIDN*</label>
            <input name="nidn" placeholder="NIDN"
                   style="width:100%;padding:10px;border:1px solid #ddd;border-radius:10px;">
            <div style="height:10px;"></div>
            <label>Prodi (opsional)</label>
            <input name="prodi" placeholder="Prodi"
                   style="width:100%;padding:10px;border:1px solid #ddd;border-radius:10px;">
          </div>

          <!-- MAHASISWA FIELD -->
          <div id="mhsField" style="display:none; grid-column:1 / -1;">
            <div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;">
              <div>
                <label>NIM/NPM*</label>
                <input name="npm" placeholder="NIM/NPM"
                       style="width:100%;padding:10px;border:1px solid #ddd;border-radius:10px;">
              </div>

              <div>
                <label>Angkatan*</label>
                <input name="angkatan" type="number" min="2000" max="2100" placeholder="contoh: 2023"
                       style="width:100%;padding:10px;border:1px solid #ddd;border-radius:10px;">
              </div>

              <div style="color:#666;font-size:12px;align-self:end;">
                Semester aktif dihitung otomatis dari angkatan + semester aktif sistem.
              </div>
            </div>

            <div style="margin-top:10px;">
              <label>Prodi*</label>
              <input name="prodi" placeholder="Prodi"
                     style="width:100%;padding:10px;border:1px solid #ddd;border-radius:10px;">
            </div>
          </div>
        </div>

        <div style="display:flex;gap:10px;margin-top:12px;flex-wrap:wrap;">
          <button class="btn" type="submit">Simpan User</button>
          <a class="btn" href="<?= e(url('admin/users/index.php')) ?>">Batal</a>
        </div>
      </form>
    </section>
  </main>
</div>

<script>
function toggleFields() {
  const role = document.getElementById('roleSelect').value;
  const dosenField = document.getElementById('dosenField');
  const mhsField = document.getElementById('mhsField');

  dosenField.style.display = (role === 'dosen') ? 'block' : 'none';
  mhsField.style.display = (role === 'mahasiswa') ? 'block' : 'none';
}
toggleFields();
</script>

<?php include __DIR__ . '/../../partials/footer.php'; ?>
