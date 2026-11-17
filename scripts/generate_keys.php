<?php
$config = require __DIR__ . '/../config/config.php';

if (!extension_loaded('openssl')) {
    fwrite(STDERR, "Ekstensi OpenSSL PHP belum aktif.\n");
    exit(1);
}

$passphrase = $config['private_key_passphrase'] ?? null;
if (!is_string($passphrase) || strlen($passphrase) < 20 || str_starts_with($passphrase, 'GANTI_')) {
    fwrite(STDERR, "Atur private_key_passphrase (minimal 20 karakter) di config/local.php terlebih dahulu.\n");
    exit(1);
}

$keyDirectory = dirname($config['private_key_path']);
if (!is_dir($keyDirectory) && !mkdir($keyDirectory, 0700, true) && !is_dir($keyDirectory)) {
    throw new RuntimeException('Folder penyimpanan kunci tidak dapat dibuat.');
}

$keyPair = openssl_pkey_new([
    'private_key_type' => OPENSSL_KEYTYPE_RSA,
    'private_key_bits' => 2048,
]);
if ($keyPair === false) {
    throw new RuntimeException('Pembuatan pasangan kunci RSA gagal.');
}

$privatePem = '';
if (!openssl_pkey_export($keyPair, $privatePem, $passphrase)) {
    throw new RuntimeException('Private key gagal diekspor.');
}
$details = openssl_pkey_get_details($keyPair);
if (!is_array($details) || empty($details['key'])) {
    throw new RuntimeException('Public key gagal dibuat.');
}

if (file_exists($config['private_key_path']) || file_exists($config['public_key_path'])) {
    fwrite(STDERR, "Kunci sudah ada. Hentikan agar kunci lama tidak tertimpa. Hapus/backup kunci lama secara manual bila memang ingin membuat ulang.\n");
    exit(1);
}

if (file_put_contents($config['private_key_path'], $privatePem, LOCK_EX) === false ||
    file_put_contents($config['public_key_path'], $details['key'], LOCK_EX) === false) {
    throw new RuntimeException('File kunci gagal disimpan.');
}

@chmod($config['private_key_path'], 0600);
@chmod($config['public_key_path'], 0644);
echo "Pasangan kunci RSA berhasil dibuat di storage/keys/.\n";
echo "Jaga private.pem dan passphrase. Jangan unggah keduanya ke repositori publik.\n";
