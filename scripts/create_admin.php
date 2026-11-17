<?php
require_once __DIR__ . '/../app/Database.php';
$config = require __DIR__ . '/../config/config.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Script hanya boleh dijalankan melalui terminal.\n");
}

$username = $argv[1] ?? 'admin';
if (!preg_match('/^[A-Za-z0-9_.-]{3,60}$/', $username)) {
    fwrite(STDERR, "Username harus 3-60 karakter dan hanya berisi huruf, angka, titik, garis bawah, atau strip.\n");
    exit(1);
}

fwrite(STDOUT, "Masukkan password admin (minimal 10 karakter): ");
$password = trim((string) fgets(STDIN));
if (strlen($password) < 10) {
    fwrite(STDERR, "Password terlalu pendek.\n");
    exit(1);
}
fwrite(STDOUT, "Ulangi password: ");
$confirmation = trim((string) fgets(STDIN));
if (!hash_equals($password, $confirmation)) {
    fwrite(STDERR, "Konfirmasi password tidak cocok.\n");
    exit(1);
}

$pdo = Database::connection($config);
$stmt = $pdo->prepare('INSERT INTO admins (username, password_hash) VALUES (:username, :password_hash)');
try {
    $stmt->execute([
        ':username' => $username,
        ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
    ]);
    echo "Admin '{$username}' berhasil dibuat.\n";
} catch (PDOException $exception) {
    if ($exception->getCode() === '23000') {
        fwrite(STDERR, "Username tersebut sudah digunakan.\n");
        exit(1);
    }
    throw $exception;
}
