<?php
require_once __DIR__ . '/../app/bootstrap.php';

$page = $_GET['page'] ?? 'dashboard';
$allowedPages = ['login', 'dashboard', 'create', 'edit'];
if (!in_array($page, $allowedPages, true)) {
    http_response_code(404);
    $page = 'dashboard';
}

// Proses semua perubahan data melalui POST dan validasi CSRF.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'login') {
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $stmt = db()->prepare('SELECT id, username, password_hash FROM admins WHERE username = :username LIMIT 1');
        $stmt->execute([':username' => $username]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($password, $admin['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['admin_id'] = (int) $admin['id'];
            $_SESSION['admin_username'] = $admin['username'];
            set_flash('success', 'Berhasil masuk. Konten akan didekripsi hanya untuk sesi admin yang terautentikasi.');
            redirect('index.php');
        }

        set_flash('error', 'Username atau password salah.');
        redirect('index.php?page=login');
    }

    if ($action === 'logout') {
        require_login();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
        redirect('index.php?page=login');
    }

    require_login();

    if ($action === 'create') {
        $title = trim((string) ($_POST['title'] ?? ''));
        $body = trim((string) ($_POST['body'] ?? ''));
        if ($title === '' || $body === '') {
            set_flash('error', 'Judul dan isi konten wajib diisi.');
            redirect('index.php?page=create');
        }

        $encrypted = crypto_service()->encrypt(['title' => $title, 'body' => $body]);
        $stmt = db()->prepare(
            'INSERT INTO contents (encrypted_key, iv, auth_tag, ciphertext, crypto_version)
             VALUES (:encrypted_key, :iv, :auth_tag, :ciphertext, :crypto_version)'
        );
        $stmt->execute([
            ':encrypted_key' => $encrypted['encrypted_key'],
            ':iv' => $encrypted['iv'],
            ':auth_tag' => $encrypted['auth_tag'],
            ':ciphertext' => $encrypted['ciphertext'],
            ':crypto_version' => $encrypted['crypto_version'],
        ]);
        set_flash('success', 'Konten berhasil disimpan dalam bentuk ciphertext.');
        redirect('index.php');
    }

    if ($action === 'update') {
        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
        $title = trim((string) ($_POST['title'] ?? ''));
        $body = trim((string) ($_POST['body'] ?? ''));
        if (!$id || $title === '' || $body === '') {
            set_flash('error', 'Data perubahan tidak valid.');
            redirect('index.php');
        }

        $encrypted = crypto_service()->encrypt(['title' => $title, 'body' => $body]);
        $stmt = db()->prepare(
            'UPDATE contents
             SET encrypted_key = :encrypted_key, iv = :iv, auth_tag = :auth_tag,
                 ciphertext = :ciphertext, crypto_version = :crypto_version
             WHERE id = :id'
        );
        $stmt->execute([
            ':encrypted_key' => $encrypted['encrypted_key'],
            ':iv' => $encrypted['iv'],
            ':auth_tag' => $encrypted['auth_tag'],
            ':ciphertext' => $encrypted['ciphertext'],
            ':crypto_version' => $encrypted['crypto_version'],
            ':id' => $id,
        ]);
        set_flash('success', 'Konten berhasil diperbarui dan dienkripsi ulang.');
        redirect('index.php');
    }

    if ($action === 'delete') {
        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
        if ($id) {
            $stmt = db()->prepare('DELETE FROM contents WHERE id = :id');
            $stmt->execute([':id' => $id]);
            set_flash('success', 'Konten berhasil dihapus.');
        } else {
            set_flash('error', 'ID konten tidak valid.');
        }
        redirect('index.php');
    }
}

if ($page === 'login' && is_logged_in()) {
    redirect('index.php');
}
if ($page !== 'login') {
    require_login();
}

$flash = take_flash();
$editItem = null;
$items = [];

if ($page === 'dashboard') {
    $rows = db()->query('SELECT * FROM contents ORDER BY updated_at DESC, id DESC')->fetchAll();
    foreach ($rows as $row) {
        try {
            $plain = crypto_service()->decrypt($row);
            $items[] = [
                'id' => (int) $row['id'],
                'title' => (string) ($plain['title'] ?? ''),
                'body' => (string) ($plain['body'] ?? ''),
                'updated_at' => $row['updated_at'],
                'error' => false,
            ];
        } catch (Throwable $exception) {
            $items[] = [
                'id' => (int) $row['id'],
                'title' => 'Konten tidak dapat didekripsi',
                'body' => 'Periksa private key, passphrase, atau integritas data.',
                'updated_at' => $row['updated_at'],
                'error' => true,
            ];
        }
    }
}

if ($page === 'edit') {
    $id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
    if (!$id) {
        set_flash('error', 'ID konten tidak valid.');
        redirect('index.php');
    }
    $stmt = db()->prepare('SELECT * FROM contents WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    if (!$row) {
        set_flash('error', 'Konten tidak ditemukan.');
        redirect('index.php');
    }
    $plain = crypto_service()->decrypt($row);
    $editItem = ['id' => (int) $row['id'], 'title' => $plain['title'] ?? '', 'body' => $plain['body'] ?? ''];
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $page === 'login' ? 'Login Admin' : 'Dashboard Admin' ?> | Secure Content</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="<?= $page === 'login' ? 'login-page' : '' ?>">
<?php if ($page === 'login'): ?>
    <main class="login-shell">
        <section class="login-card">
            <div class="brand-mark">SC</div>
            <p class="eyebrow">SECURE CONTENT ADMIN</p>
            <h1>Masuk ke halaman admin</h1>
            <p class="muted">Akses terbatas untuk administrator yang memiliki akun.</p>
            <?php if ($flash): ?>
                <div class="alert <?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
            <?php endif; ?>
            <form method="post" class="form-stack">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="login">
                <label>Username<input type="text" name="username" autocomplete="username" required autofocus></label>
                <label>Password<input type="password" name="password" autocomplete="current-password" required></label>
                <button class="button primary full" type="submit">Login</button>
            </form>
            <p class="login-foot">AES-256-GCM <span>•</span> RSA-OAEP</p>
        </section>
    </main>
<?php else: ?>
    <div class="app-layout">
        <aside class="sidebar">
            <a class="brand" href="index.php"><span class="brand-mark small">SC</span><span>Secure<span class="brand-light">Content</span></span></a>
            <div class="side-label">MENU UTAMA</div>
            <a class="nav-link <?= $page === 'dashboard' ? 'active' : '' ?>" href="index.php"><span>▦</span> Dashboard</a>
            <a class="nav-link <?= $page === 'create' ? 'active' : '' ?>" href="index.php?page=create"><span>＋</span> Tambah Konten</a>
            <div class="sidebar-bottom">
                <div class="security-note"><span class="shield">✓</span><div><strong>Enkripsi aktif</strong><small>AES + RSA hybrid</small></div></div>
                <div class="admin-mini"><div class="avatar">AD</div><div><strong><?= e($_SESSION['admin_username'] ?? 'Admin') ?></strong><small>Administrator</small></div></div>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="logout">
                    <button type="submit" class="logout-button">Keluar dari akun ↗</button>
                </form>
            </div>
        </aside>

        <main class="main-content">
            <header class="topbar">
                <div><span class="breadcrumb">Admin /</span> <strong><?= $page === 'create' ? 'Tambah Konten' : ($page === 'edit' ? 'Edit Konten' : 'Dashboard') ?></strong></div>
                <div class="topbar-right"><span class="status-dot"></span> Sistem terlindungi <span class="top-avatar">AD</span></div>
            </header>

            <div class="content-wrap">
                <?php if ($flash): ?>
                    <div class="alert <?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
                <?php endif; ?>

                <?php if ($page === 'dashboard'): ?>
                    <div class="page-heading">
                        <div><p class="eyebrow">RINGKASAN SISTEM</p><h1>Dashboard Admin</h1><p class="muted">Kelola informasi dengan perlindungan kriptografi berlapis.</p></div>
                        <a class="button primary" href="index.php?page=create">＋ Tambah Konten</a>
                    </div>
                    <section class="stats-grid">
                        <article class="stat-card"><div class="stat-icon purple">▤</div><div><p>Total konten</p><strong><?= count($items) ?></strong><small>Data yang tersimpan</small></div></article>
                        <article class="stat-card"><div class="stat-icon green">⌑</div><div><p>Enkripsi simetris</p><strong>AES-256</strong><small>GCM authenticated encryption</small></div></article>
                        <article class="stat-card"><div class="stat-icon blue">◇</div><div><p>Enkripsi asimetris</p><strong>RSA-2048</strong><small>OAEP key protection</small></div></article>
                    </section>
                    <section class="panel">
                        <div class="panel-heading"><div><h2>Konten tersimpan</h2><p>Konten ditampilkan setelah proses dekripsi di sisi server.</p></div><span class="secure-chip">● Enkripsi aktif</span></div>
                        <?php if (!$items): ?>
                            <div class="empty-state"><div class="empty-icon">▤</div><h3>Belum ada konten</h3><p>Tambahkan konten pertama untuk menguji proses enkripsi dan dekripsi.</p><a class="button primary" href="index.php?page=create">Tambah Konten</a></div>
                        <?php else: ?>
                            <div class="content-list">
                                <?php foreach ($items as $item): ?>
                                    <article class="content-row">
                                        <div class="content-symbol">▤</div>
                                        <div class="content-info"><h3><?= e($item['title']) ?> <?php if ($item['error']): ?><span class="error-chip">Periksa kunci</span><?php endif; ?></h3><p><?= e(short_text($item['body'], 150)) ?></p><small>Diperbarui <?= e(date('d M Y, H:i', strtotime($item['updated_at']))) ?> · ID #<?= (int) $item['id'] ?></small></div>
                                        <div class="row-actions"><a class="button outline small-button" href="index.php?page=edit&id=<?= (int) $item['id'] ?>" <?= $item['error'] ? 'aria-disabled="true" onclick="return false"' : '' ?>>Edit</a><form method="post" onsubmit="return confirm('Hapus konten ini?')"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $item['id'] ?>"><button class="button danger small-button" type="submit">Hapus</button></form></div>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </section>
                    <section class="info-banner"><div class="info-icon">i</div><div><strong>Bagaimana data dilindungi?</strong><p>Isi judul dan deskripsi dienkripsi dengan AES-256-GCM. Kunci AES acak untuk setiap konten kemudian diamankan menggunakan public key RSA. Private key digunakan server untuk membuka kunci AES saat admin melihat konten.</p></div></section>

                <?php elseif ($page === 'create' || $page === 'edit'): ?>
                    <div class="page-heading"><div><p class="eyebrow">MANAJEMEN INFORMASI</p><h1><?= $page === 'create' ? 'Tambah Konten Baru' : 'Edit Konten' ?></h1><p class="muted">Data akan dienkripsi sebelum disimpan ke database.</p></div><a class="button outline" href="index.php">← Kembali</a></div>
                    <section class="panel form-panel">
                        <div class="panel-heading"><div><h2>Informasi konten</h2><p>Masukkan judul dan isi informasi yang akan dikelola.</p></div><span class="secure-chip">● Akan dienkripsi</span></div>
                        <form method="post" class="form-stack content-form">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="action" value="<?= $page === 'create' ? 'create' : 'update' ?>">
                            <?php if ($page === 'edit'): ?><input type="hidden" name="id" value="<?= (int) $editItem['id'] ?>"><?php endif; ?>
                            <label>Judul konten<input type="text" name="title" maxlength="200" value="<?= e($editItem['title'] ?? '') ?>" placeholder="Contoh: Pengumuman Akademik" required></label>
                            <label>Isi konten<textarea name="body" rows="9" placeholder="Tuliskan informasi yang ingin disimpan..." required><?= e($editItem['body'] ?? '') ?></textarea></label>
                            <div class="encryption-hint"><span>🔒</span><p><strong>Perlindungan otomatis</strong><br>Judul dan isi konten dienkripsi menggunakan AES-256-GCM. Kunci AES dibungkus dengan RSA sebelum disimpan.</p></div>
                            <div class="form-actions"><a class="button outline" href="index.php">Batal</a><button class="button primary" type="submit"><?= $page === 'create' ? 'Simpan & Enkripsi' : 'Perbarui & Enkripsi' ?></button></div>
                        </form>
                    </section>
                <?php endif; ?>
                <footer class="footer">Secure Content Admin <span>·</span> Implementasi Kriptografi Simetris dan Asimetris</footer>
            </div>
        </main>
    </div>
<?php endif; ?>
</body>
</html>
