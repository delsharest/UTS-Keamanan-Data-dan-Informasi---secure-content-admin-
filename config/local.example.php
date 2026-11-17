<?php
// Salin file ini menjadi local.php, lalu sesuaikan dengan konfigurasi komputer Anda.
return [
    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'kriptografi_admin',
        'user' => 'root',
        'pass' => '',
    ],
    // Ganti dengan passphrase panjang. Gunakan nilai yang sama saat membuat pasangan kunci.
    'private_key_passphrase' => 'GANTI_DENGAN_PASSPHRASE_UNIK_MINIMAL_20_KARAKTER',
];
