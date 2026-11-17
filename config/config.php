<?php
$defaults = [
    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'kriptografi_admin',
        'user' => 'root',
        'pass' => '',
    ],
    'private_key_passphrase' => getenv('APP_PRIVATE_KEY_PASSPHRASE') ?: null,
    'private_key_path' => __DIR__ . '/../storage/keys/private.pem',
    'public_key_path' => __DIR__ . '/../storage/keys/public.pem',
];

$localFile = __DIR__ . '/local.php';
$local = file_exists($localFile) ? require $localFile : [];

return array_replace_recursive($defaults, is_array($local) ? $local : []);
