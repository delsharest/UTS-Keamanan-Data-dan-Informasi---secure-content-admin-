<?php
$config = require __DIR__ . '/../config/config.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/CryptoService.php';

$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function db(): PDO
{
    global $config;
    return Database::connection($config);
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function is_logged_in(): bool
{
    return isset($_SESSION['admin_id']);
}

function require_login(): void
{
    if (!is_logged_in()) {
        redirect('index.php?page=login');
    }
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function short_text(string $value, int $width = 150): string
{
    if (function_exists('mb_strimwidth')) {
        return mb_strimwidth($value, 0, $width, '…', 'UTF-8');
    }
    return strlen($value) > $width ? substr($value, 0, $width) . '…' : $value;
}

function verify_csrf(): void
{
    $submitted = $_POST['csrf_token'] ?? '';
    if (!is_string($submitted) || !hash_equals(csrf_token(), $submitted)) {
        http_response_code(419);
        exit('Permintaan tidak valid (CSRF token tidak cocok). Muat ulang halaman, lalu coba lagi.');
    }
}

function set_flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function take_flash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return is_array($flash) ? $flash : null;
}

function crypto_service(): CryptoService
{
    global $config;
    return new CryptoService(
        $config['public_key_path'],
        $config['private_key_path'],
        $config['private_key_passphrase']
    );
}
