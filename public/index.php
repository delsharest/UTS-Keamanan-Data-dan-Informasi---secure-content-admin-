<?php
require_once __DIR__ . '/../app/bootstrap.php';

$defaultPage = is_logged_in() ? 'dashboard' : 'home';
$page = $_GET['page'] ?? $defaultPage;
$allowedPages = ['home', 'login', 'dashboard', 'create', 'edit'];
if (!in_array($page, $allowedPages, true)) {
    http_response_code(404);
    $page = is_logged_in() ? 'dashboard' : 'home';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'login') {
        $privateKeyPem = trim((string) ($_POST['private_key'] ?? ''));
        $keyIsCorrect = false;

        if ($privateKeyPem !== '') {
            try {
                $keyIsCorrect = crypto_service()->privateKeyMatchesPublicKey($privateKeyPem);
            } catch (Throwable $exception) {
                $keyIsCorrect = false;
            }
        }

        session_regenerate_id(true);
        $_SESSION['access_granted'] = true;
        $_SESSION['can_decrypt'] = $keyIsCorrect;

        if ($keyIsCorrect) {
            // Stored in the server-side PHP session only for the active session.
            // It is removed when the user logs out or the session expires.
            $_SESSION['private_key_pem'] = $privateKeyPem;
            set_flash('success', 'Private key sesuai. Mode dekripsi aktif dan konten dapat dibaca.');
        } else {
            unset($_SESSION['private_key_pem']);
            set_flash('warning', 'Dashboard dibuka dalam mode ciphertext. Private key tidak cocok, sehingga konten tetap ditampilkan dalam bentuk terenkripsi.');
        }

        redirect('index.php');
    }

    if ($action === 'logout') {
        require_login();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
        redirect('index.php');
    }

    require_decryption();

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
        set_flash('success', 'Konten berhasil dienkripsi dan disimpan ke database.');
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

if ($page === 'home' && is_logged_in()) {
    redirect('index.php');
}
if ($page === 'login' && is_logged_in()) {
    redirect('index.php');
}
if (in_array($page, ['dashboard', 'create', 'edit'], true)) {
    require_login();
}
if (in_array($page, ['create', 'edit'], true)) {
    require_decryption();
}

$flash = take_flash();
$editItem = null;
$items = [];
$totalContents = 0;

if ($page === 'dashboard') {
    $rows = db()->query('SELECT * FROM contents ORDER BY updated_at DESC, id DESC')->fetchAll();
    $totalContents = count($rows);

    foreach ($rows as $row) {
        if (!can_decrypt()) {
            $items[] = [
                'id' => (int) $row['id'],
                'title' => 'DATA TERENKRIPSI #' . (int) $row['id'],
                'body' => (string) $row['ciphertext'],
                'encrypted' => true,
                'encrypted_key' => (string) $row['encrypted_key'],
                'iv' => (string) $row['iv'],
                'auth_tag' => (string) $row['auth_tag'],
                'crypto_version' => (string) $row['crypto_version'],
                'updated_at' => $row['updated_at'],
                'error' => false,
            ];
            continue;
        }

        try {
            $plain = crypto_service()->decrypt($row, $_SESSION['private_key_pem']);
            $items[] = [
                'id' => (int) $row['id'],
                'title' => (string) ($plain['title'] ?? ''),
                'body' => (string) ($plain['body'] ?? ''),
                'encrypted' => false,
                'updated_at' => $row['updated_at'],
                'error' => false,
            ];
        } catch (Throwable $exception) {
            // Do not leak a partial plaintext if the record cannot be authenticated/decrypted.
            $items[] = [
                'id' => (int) $row['id'],
                'title' => 'DATA TIDAK DAPAT DIDEKRIPSI #' . (int) $row['id'],
                'body' => (string) $row['ciphertext'],
                'encrypted' => true,
                'encrypted_key' => (string) $row['encrypted_key'],
                'iv' => (string) $row['iv'],
                'auth_tag' => (string) $row['auth_tag'],
                'crypto_version' => (string) $row['crypto_version'],
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

    $plain = crypto_service()->decrypt($row, $_SESSION['private_key_pem']);
    $editItem = ['id' => (int) $row['id'], 'title' => $plain['title'] ?? '', 'body' => $plain['body'] ?? ''];
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#101a33">
    <meta name="description" content="<?= ui_attr('Secure Content — demonstrasi sistem informasi dengan kriptografi AES-256-GCM dan RSA-2048.') ?>">
    <title><?= ui_attr(($page === 'home' ? 'Beranda' : ($page === 'login' ? 'Akses Private Key' : 'Dashboard')) . ' | Secure Content') ?></title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="page-<?= e($page) ?> <?= is_logged_in() && !can_decrypt() ? 'cipher-ui-mode' : '' ?>">
<?php if ($page === 'home'): ?>
    <div class="landing-shell">
        <header class="landing-header">
            <a class="brand" href="index.php" aria-label="Secure Content beranda"><span class="brand-mark">SC</span><span>Secure<span class="brand-light">Content</span></span></a>
            <nav class="landing-nav" aria-label="Navigasi utama">
                <a href="#tentang">Tentang</a>
                <a href="#teknologi">Teknologi</a>
                <a href="#alur">Cara kerja</a>
            </nav>
            <a class="button button-light" href="index.php?page=login">Masuk dengan Private Key <span aria-hidden="true">↗</span></a>
        </header>

        <main>
            <section class="hero-section">
                <div class="hero-copy">
                    <span class="eyebrow-pill"><span class="pulse-dot"></span> HYBRID ENCRYPTION SYSTEM</span>
                    <h1>Informasi penting.<br><span>Perlindungan berlapis.</span></h1>
                    <p class="hero-lead">Secure Content adalah aplikasi sistem informasi yang menerapkan kriptografi simetris dan asimetris untuk melindungi konten sebelum disimpan ke database.</p>
                    <div class="hero-actions">
                        <a class="button button-primary" href="index.php?page=login">Masuk ke Dashboard <span aria-hidden="true">→</span></a>
                        <a class="text-link" href="#tentang">Pelajari kriptografi <span aria-hidden="true">↓</span></a>
                    </div>
                    <div class="hero-proof"><span class="proof-icon">✓</span><span>Konten disimpan sebagai ciphertext</span><span class="proof-separator">·</span><span>AES-256-GCM + RSA-2048</span></div>
                </div>
                <div class="hero-visual" aria-label="Ilustrasi perlindungan data">
                    <div class="visual-orbit orbit-one"></div><div class="visual-orbit orbit-two"></div>
                    <div class="visual-glow"></div>
                    <div class="encryption-card">
                        <div class="encryption-card-top"><span class="mini-shield">◇</span><span>SECURE VAULT</span><span class="live-indicator">● ACTIVE</span></div>
                        <div class="vault-lock"><span>⌑</span></div>
                        <div class="vault-title">Your data, protected.</div>
                        <div class="vault-subtitle">Hybrid encryption in action</div>
                        <div class="cipher-lines"><i></i><i></i><i></i><i></i></div>
                        <div class="vault-tags"><span>AES-256</span><span>RSA-2048</span><span>GCM</span></div>
                    </div>
                    <div class="float-chip chip-top"><span>01</span> ENCRYPT</div>
                    <div class="float-chip chip-bottom"><span>02</span> DECRYPT WITH KEY</div>
                </div>
            </section>

            <section class="metrics-strip" aria-label="Teknologi yang digunakan">
                <div><strong>AES-256-GCM</strong><span>Enkripsi simetris</span></div>
                <div><strong>RSA-2048</strong><span>Enkripsi asimetris</span></div>
                <div><strong>PHP + MySQL</strong><span>Teknologi aplikasi</span></div>
                <div><strong>Private Key</strong><span>Akses dekripsi</span></div>
            </section>

            <section class="section-block about-section" id="tentang">
                <div class="section-kicker">01 / MEMAHAMI DASAR</div>
                <div class="section-heading"><h2>Apa itu <span>kriptografi?</span></h2><p>Kriptografi merupakan teknik untuk melindungi informasi dengan mengubah data yang dapat dibaca menjadi bentuk tersandi, sehingga hanya pihak yang memiliki kunci yang sesuai dapat mengembalikannya.</p></div>
                <div class="concept-grid">
                    <article class="concept-card"><div class="concept-icon purple-icon">⌑</div><h3>Data asli (plaintext)</h3><p>Informasi sebelum dienkripsi, misalnya judul dan deskripsi konten yang dimasukkan administrator.</p><span class="concept-caption">READABLE INFORMATION</span></article>
                    <article class="concept-card"><div class="concept-icon blue-icon">▧</div><h3>Data tersandi (ciphertext)</h3><p>Hasil enkripsi yang tidak menampilkan isi asli dan disimpan ke database sebagai rangkaian data tersandi.</p><span class="concept-caption">ENCRYPTED INFORMATION</span></article>
                    <article class="concept-card"><div class="concept-icon green-icon">◈</div><h3>Kunci kriptografi</h3><p>Komponen yang digunakan dalam proses enkripsi dan dekripsi. Private key yang cocok diperlukan untuk membaca kembali konten.</p><span class="concept-caption">CONTROLLED ACCESS</span></article>
                </div>
            </section>

            <section class="section-block technology-section" id="teknologi">
                <div class="section-kicker">02 / TEKNOLOGI PERLINDUNGAN</div>
                <div class="section-heading"><h2>Dua algoritma, <span>satu perlindungan.</span></h2><p>Aplikasi menggabungkan kecepatan algoritma simetris dengan mekanisme pasangan kunci pada algoritma asimetris.</p></div>
                <div class="technology-grid">
                    <article class="technology-card aes-card"><div class="tech-top"><span class="tech-label">SYMMETRIC ENCRYPTION</span><span class="tech-number">01</span></div><div class="tech-symbol">⌑</div><h3>AES-256-GCM</h3><p>Advanced Encryption Standard menggunakan satu kunci rahasia untuk mengenkripsi dan mendekripsi data. Mode GCM juga menghasilkan authentication tag untuk memeriksa integritas data.</p><div class="tech-footer"><span>256-bit key</span><span>Authenticated encryption</span></div></article>
                    <article class="technology-card rsa-card"><div class="tech-top"><span class="tech-label">ASYMMETRIC ENCRYPTION</span><span class="tech-number">02</span></div><div class="tech-symbol">◇</div><h3>RSA-2048 + OAEP</h3><p>RSA menggunakan pasangan public key dan private key. Pada aplikasi ini, public key melindungi kunci AES, sedangkan private key yang sesuai digunakan untuk membuka kunci AES tersebut.</p><div class="tech-footer"><span>2048-bit key pair</span><span>OAEP padding</span></div></article>
                </div>
            </section>

            <section class="section-block process-section" id="alur">
                <div class="section-kicker">03 / ALUR SISTEM</div>
                <div class="section-heading"><h2>Bagaimana sistem <span>bekerja?</span></h2><p>Konten selalu disimpan dalam bentuk terenkripsi. Tampilan plaintext hanya tersedia ketika private key yang dimasukkan cocok dengan pasangan public key aplikasi.</p></div>
                <div class="process-flow">
                    <div class="process-step"><span class="step-index">01</span><div class="step-icon">✎</div><h3>Input konten</h3><p>Administrator menambahkan judul dan isi informasi.</p></div>
                    <div class="process-arrow">→</div>
                    <div class="process-step"><span class="step-index">02</span><div class="step-icon">⌑</div><h3>Enkripsi AES</h3><p>Konten dienkripsi menggunakan kunci AES acak.</p></div>
                    <div class="process-arrow">→</div>
                    <div class="process-step"><span class="step-index">03</span><div class="step-icon">◇</div><h3>Proteksi RSA</h3><p>Kunci AES diamankan dengan public key RSA.</p></div>
                    <div class="process-arrow">→</div>
                    <div class="process-step"><span class="step-index">04</span><div class="step-icon">▤</div><h3>Simpan database</h3><p>Ciphertext dan komponen kriptografi disimpan.</p></div>
                </div>
                <div class="key-note"><span class="key-note-icon">⌑</span><div><strong>Private key menentukan mode tampilan</strong><p>Private key yang cocok mengaktifkan dekripsi. Jika kunci yang dimasukkan salah, dashboard tetap dapat dibuka tetapi hanya menampilkan ciphertext.</p></div></div>
            </section>

            <section class="final-cta"><div><span class="section-kicker">READY TO EXPLORE?</span><h2>Uji perlindungan data secara langsung.</h2><p>Masuk menggunakan private key untuk melihat hasil dekripsi atau amati ciphertext dengan kunci yang tidak sesuai.</p></div><a class="button button-primary" href="index.php?page=login">Buka Aplikasi <span aria-hidden="true">→</span></a></section>
        </main>
        <footer class="landing-footer"><a class="brand" href="index.php"><span class="brand-mark">SC</span><span>Secure<span class="brand-light">Content</span></span></a><span>Implementasi Kriptografi Simetris dan Asimetris</span><span>PHP · MySQL · OpenSSL</span></footer>
    </div>

<?php elseif ($page === 'login'): ?>
    <main class="auth-shell">
        <div class="auth-aside">
            <a class="brand" href="index.php"><span class="brand-mark">SC</span><span>Secure<span class="brand-light">Content</span></span></a>
            <div class="auth-aside-copy"><span class="eyebrow-pill"><span class="pulse-dot"></span> PRIVATE KEY ACCESS</span><h1>Kunci yang tepat membuka informasi.</h1><p>Masukkan private key RSA yang berpasangan dengan public key aplikasi. Kunci yang cocok akan mengaktifkan dekripsi konten.</p><div class="auth-algorithms"><span>AES-256-GCM</span><span>RSA-2048</span><span>OAEP</span></div></div>
            <div class="auth-aside-bottom">SECURE CONTENT SYSTEM <span>·</span> HYBRID CRYPTOGRAPHY</div>
        </div>
        <section class="auth-main">
            <div class="auth-card-wrap">
                <a class="back-link" href="index.php">← Kembali ke beranda</a>
                <div class="auth-card">
                    <div class="auth-icon">⌑</div>
                    <span class="section-kicker">ADMINISTRATOR ACCESS</span>
                    <h2>Masuk dengan private key</h2>
                    <p class="auth-description">Tempelkan seluruh isi file <code>private.pem</code>, termasuk baris BEGIN dan END.</p>
                    <?php if ($flash): ?><div class="alert <?= e($flash['type']) ?>"><?= e($flash['message']) ?></div><?php endif; ?>
                    <form method="post" class="form-stack auth-form">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="login">
                        <label for="private-key">Private key (PEM)</label>
                        <textarea id="private-key" name="private_key" rows="9" placeholder="-----BEGIN ENCRYPTED PRIVATE KEY-----&#10;Tempelkan isi private.pem di sini&#10;-----END ENCRYPTED PRIVATE KEY-----" autocomplete="off" autocapitalize="off" spellcheck="false" required></textarea>
                        <div class="key-helper"><span>i</span><p>Gunakan file <strong>storage/keys/private.pem</strong> milik aplikasi ini. Jangan gunakan public.pem.</p></div>
                        <button class="button button-primary full-button" type="submit">Lanjutkan ke Dashboard <span>→</span></button>
                    </form>
                    <div class="auth-footnote"><span class="lock-mini">▣</span> Private key diproses oleh aplikasi untuk memeriksa pasangan kunci.</div>
                </div>
                <p class="auth-bottom-note">Aplikasi pembelajaran lokal. Jangan memasukkan private key ke situs atau server yang tidak dipercaya.</p>
            </div>
        </section>
    </main>

<?php else: ?>
    <div class="app-layout">
        <aside class="sidebar">
            <a class="brand" href="index.php">
                <span class="brand-mark small"><?= ui_text('SC') ?></span>
                <span><?= ui_text('Secure') ?><span class="brand-light"><?= ui_text('Content') ?></span></span>
            </a>
            <div class="side-label"><?= ui_text('WORKSPACE') ?></div>
            <a class="nav-link <?= $page === 'dashboard' ? 'active' : '' ?>" href="index.php"><span><?= ui_text('▦') ?></span> <?= ui_text('Dashboard') ?></a>
            <?php if (can_decrypt()): ?><a class="nav-link <?= $page === 'create' ? 'active' : '' ?>" href="index.php?page=create"><span><?= ui_text('＋') ?></span> <?= ui_text('Tambah Konten') ?></a><?php endif; ?>
            <div class="sidebar-bottom">
                <div class="security-note <?= can_decrypt() ? 'secure-mode' : 'cipher-mode' ?>">
                    <span class="shield"><?= ui_text(can_decrypt() ? '✓' : '⌑') ?></span>
                    <div><strong><?= ui_text(can_decrypt() ? 'Mode dekripsi' : 'Mode ciphertext') ?></strong><small><?= ui_text(can_decrypt() ? 'Private key cocok' : 'Private key tidak cocok') ?></small></div>
                </div>
                <div class="admin-mini"><div class="avatar"><?= ui_text('SC') ?></div><div><strong><?= ui_text('Administrator') ?></strong><small><?= ui_text(can_decrypt() ? 'Akses terdekripsi' : 'Akses ciphertext') ?></small></div></div>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="logout">
                    <button type="submit" class="logout-button"><?= ui_text('Keluar dari sesi') ?> <span><?= ui_text('↗') ?></span></button>
                </form>
            </div>
        </aside>

        <main class="main-content">
            <header class="topbar">
                <div><span class="breadcrumb"><?= ui_text('Secure Content /') ?></span> <strong><?= ui_text($page === 'create' ? 'Tambah Konten' : ($page === 'edit' ? 'Edit Konten' : 'Dashboard')) ?></strong></div>
                <div class="topbar-right"><span class="status-dot <?= can_decrypt() ? '' : 'warning-dot' ?>"></span><?= ui_text(can_decrypt() ? 'Private key valid' : 'Ciphertext only') ?><span class="top-avatar"><?= ui_text('SC') ?></span></div>
            </header>

            <div class="content-wrap">
                <?php if ($flash): ?><div class="alert <?= e($flash['type']) ?>"><?= ui_text((string) $flash['message']) ?></div><?php endif; ?>

                <?php if ($page === 'dashboard'): ?>
                    <div class="dashboard-hero">
                        <div>
                            <span class="section-kicker"><?= ui_text('SECURE WORKSPACE / OVERVIEW') ?></span>
                            <h1><?= ui_text('Dashboard') ?> <span><?= ui_text('Admin.') ?></span></h1>
                            <p><?= ui_text(can_decrypt() ? 'Private key terverifikasi. Konten ditampilkan setelah proses dekripsi.' : 'Dashboard tetap tersedia, tetapi seluruh konten ditampilkan dalam bentuk ciphertext.') ?></p>
                        </div>
                        <div class="dashboard-mode-badge <?= can_decrypt() ? 'mode-valid' : 'mode-locked' ?>">
                            <span><?= ui_text(can_decrypt() ? '✓' : '⌑') ?></span>
                            <div><strong><?= ui_text(can_decrypt() ? 'DECRYPTION ENABLED' : 'CIPHERTEXT MODE') ?></strong><small><?= ui_text(can_decrypt() ? 'RSA private key matched' : 'Key pair not matched') ?></small></div>
                        </div>
                    </div>

                    <?php if (!can_decrypt()): ?>
                        <section class="mode-warning"><div class="warning-symbol"><?= ui_text('⌑') ?></div><div><strong><?= ui_text('Konten sedang ditampilkan sebagai ciphertext') ?></strong><p><?= ui_text('Private key yang dimasukkan tidak cocok dengan public key aplikasi. Teks asli tidak ditampilkan. Keluar dari sesi, lalu masuk kembali menggunakan private.pem yang benar untuk mengaktifkan dekripsi.') ?></p></div></section>
                    <?php else: ?>
                        <section class="mode-success"><span><?= ui_text('✓') ?></span><div><strong><?= ui_text('Private key berhasil diverifikasi') ?></strong><p><?= ui_text('Konten dapat dibaca karena private key sesuai dengan pasangan public key aplikasi.') ?></p></div></section>
                    <?php endif; ?>

                    <section class="stats-grid">
                        <article class="stat-card"><div class="stat-icon purple"><?= ui_text('▤') ?></div><div><p><?= ui_text('Total data') ?></p><strong><?= ui_text((string) $totalContents) ?></strong><small><?= ui_text('Record tersimpan di database') ?></small></div></article>
                        <article class="stat-card"><div class="stat-icon green"><?= ui_text('⌑') ?></div><div><p><?= ui_text('Algoritma simetris') ?></p><strong><?= ui_text('AES-256-GCM') ?></strong><small><?= ui_text('Perlindungan konten utama') ?></small></div></article>
                        <article class="stat-card"><div class="stat-icon blue"><?= ui_text('◇') ?></div><div><p><?= ui_text('Algoritma asimetris') ?></p><strong><?= ui_text('RSA-2048') ?></strong><small><?= ui_text('Proteksi kunci AES · OAEP') ?></small></div></article>
                    </section>

                    <section class="panel content-panel">
                        <div class="panel-heading">
                            <div><span class="section-kicker"><?= ui_text('DATA REPOSITORY') ?></span><h2><?= ui_text(can_decrypt() ? 'Konten tersimpan' : 'Data dalam bentuk terenkripsi') ?></h2><p><?= ui_text(can_decrypt() ? 'Isi konten didekripsi pada sesi dengan private key yang valid.' : 'Yang ditampilkan adalah ciphertext dan komponen hasil enkripsi.') ?></p></div>
                            <?php if (can_decrypt()): ?><a class="button button-primary" href="index.php?page=create"><?= ui_text('＋ Tambah Konten') ?></a><?php else: ?><span class="secure-chip locked-chip"><?= ui_text('⌑ READ ONLY') ?></span><?php endif; ?>
                        </div>
                        <?php if (!$items): ?>
                            <div class="empty-state"><div class="empty-icon"><?= ui_text('▤') ?></div><h3><?= ui_text('Belum ada konten tersimpan') ?></h3><p><?= ui_text('Tambahkan konten setelah masuk menggunakan private key yang benar.') ?></p><?php if (can_decrypt()): ?><a class="button button-primary" href="index.php?page=create"><?= ui_text('Tambah Konten Pertama') ?></a><?php endif; ?></div>
                        <?php else: ?>
                            <div class="content-list">
                                <?php foreach ($items as $item): ?>
                                    <article class="content-row <?= !empty($item['encrypted']) ? 'encrypted-row' : '' ?>">
                                        <div class="content-symbol"><?= ui_text(!empty($item['encrypted']) ? '⌑' : '▤') ?></div>
                                        <div class="content-info">
                                            <div class="content-title-line"><h3><?= !empty($item['encrypted']) ? ui_text('RECORD ' . (string) $item['id']) : e($item['title']) ?></h3><?php if (!empty($item['encrypted'])): ?><span class="cipher-badge"><?= ui_text('CIPHERTEXT') ?></span><?php endif; ?></div>
                                            <?php if (!empty($item['encrypted'])): ?>
                                                <p class="cipher-preview"><?= e($item['body']) ?></p>
                                                <details class="crypto-details"><summary><?= ui_text('Lihat komponen data terenkripsi') ?></summary>
                                                    <div class="crypto-field"><span><?= ui_text('Encrypted AES key (RSA)') ?></span><code><?= e($item['encrypted_key']) ?></code></div>
                                                    <div class="crypto-field"><span><?= ui_text('Initialization Vector') ?></span><code><?= e($item['iv']) ?></code></div>
                                                    <div class="crypto-field"><span><?= ui_text('Authentication tag') ?></span><code><?= e($item['auth_tag']) ?></code></div>
                                                    <div class="crypto-field"><span><?= ui_text('Ciphertext (judul + isi)') ?></span><code><?= e($item['body']) ?></code></div>
                                                    <small><?= ui_text('Format: ' . (string) $item['crypto_version']) ?></small>
                                                </details>
                                            <?php else: ?>
                                                <p><?= e(short_text($item['body'], 220)) ?></p>
                                                <?php if (!empty($item['error'])): ?><span class="error-chip"><?= ui_text('Periksa integritas data') ?></span><?php endif; ?>
                                            <?php endif; ?>
                                            <?php if (can_decrypt()): ?><small><?= ui_text('Diperbarui ' . date('d M Y, H:i', strtotime($item['updated_at'])) . ' · ID #' . (string) $item['id']) ?></small><?php else: ?><small><?= ui_text('Record #' . (string) $item['id'] . ' · Konten tidak didekripsi') ?></small><?php endif; ?>
                                        </div>
                                        <?php if (can_decrypt()): ?>
                                            <div class="row-actions"><a class="button button-outline small-button" href="index.php?page=edit&id=<?= (int) $item['id'] ?>"><?= ui_text('Edit') ?></a><form method="post" onsubmit="return confirm('<?= e('Hapus konten ini?') ?>')"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $item['id'] ?>"><button class="button button-danger small-button" type="submit"><?= ui_text('Hapus') ?></button></form></div>
                                        <?php endif; ?>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </section>

                    <section class="info-banner"><div class="info-icon"><?= ui_text('i') ?></div><div><strong><?= ui_text('Ringkasan perlindungan data') ?></strong><p><?= ui_text('Judul dan isi konten digabung menjadi payload JSON, dienkripsi dengan AES-256-GCM, lalu kunci AES acak diamankan menggunakan RSA-2048 OAEP. Database menyimpan ciphertext, encrypted key, IV, dan authentication tag.') ?></p></div></section>

                <?php elseif ($page === 'create' || $page === 'edit'): ?>
                    <div class="page-heading"><div><span class="section-kicker"><?= ui_text('CONTENT MANAGEMENT') ?></span><h1><?= ui_text($page === 'create' ? 'Tambah konten baru.' : 'Perbarui konten.') ?></h1><p class="muted"><?= ui_text('Data akan dienkripsi sebelum disimpan ke database.') ?></p></div><a class="button button-outline" href="index.php"><?= ui_text('← Kembali') ?></a></div>
                    <section class="panel form-panel">
                        <div class="panel-heading"><div><span class="section-kicker"><?= ui_text('ENCRYPTED CONTENT') ?></span><h2><?= ui_text('Informasi konten') ?></h2><p><?= ui_text('Masukkan judul dan isi informasi yang akan dikelola.') ?></p></div><span class="secure-chip"><?= ui_text('✓ ENCRYPTION READY') ?></span></div>
                        <form method="post" class="form-stack content-form">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="action" value="<?= e($page === 'create' ? 'create' : 'update') ?>">
                            <?php if ($page === 'edit'): ?><input type="hidden" name="id" value="<?= (int) $editItem['id'] ?>"><?php endif; ?>
                            <label for="title"><?= ui_text('Judul konten') ?><input id="title" type="text" name="title" maxlength="200" value="<?= e($editItem['title'] ?? '') ?>" placeholder="<?= ui_attr('Contoh: Informasi Pemeliharaan Server') ?>" required></label>
                            <label for="body"><?= ui_text('Isi konten') ?><textarea id="body" name="body" rows="9" placeholder="<?= ui_attr('Tuliskan informasi yang ingin disimpan...') ?>" required><?= e($editItem['body'] ?? '') ?></textarea></label>
                            <div class="encryption-hint"><span><?= ui_text('⌑') ?></span><p><strong><?= ui_text('Enkripsi otomatis aktif') ?></strong><br><?= ui_text('Judul dan isi akan dienkripsi dengan AES-256-GCM. Kunci AES akan diamankan menggunakan public key RSA-2048.') ?></p></div>
                            <div class="form-actions"><a class="button button-outline" href="index.php"><?= ui_text('Batal') ?></a><button class="button button-primary" type="submit"><?= ui_text($page === 'create' ? 'Simpan & Enkripsi →' : 'Perbarui & Enkripsi →') ?></button></div>
                        </form>
                    </section>
                <?php endif; ?>
                <footer class="footer"><?= ui_text('SECURE CONTENT') ?> <span><?= ui_text('·') ?></span> <?= ui_text('SYMMETRIC & ASYMMETRIC CRYPTOGRAPHY') ?></footer>
            </div>
        </main>
    </div>
<?php endif; ?>

</body>
</html>
