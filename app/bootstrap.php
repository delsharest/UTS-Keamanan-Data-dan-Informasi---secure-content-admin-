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
    return !empty($_SESSION['access_granted']);
}

function can_decrypt(): bool
{
    return is_logged_in() && !empty($_SESSION['can_decrypt']) && is_string($_SESSION['private_key_pem'] ?? null);
}

function require_login(): void
{
    if (!is_logged_in()) {
        redirect('index.php?page=login');
    }
}

function require_decryption(): void
{
    require_login();
    if (!can_decrypt()) {
        set_flash('error', 'Fitur pengelolaan konten hanya tersedia ketika private key yang benar digunakan. Dashboard tetap dapat dibuka dalam mode ciphertext.');
        redirect('index.php');
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
        $config['private_key_passphrase']
    );
}

/**
 * Render every dashboard label through the same hybrid cryptography flow.
 * With a matching private key the temporary label payload is decrypted and
 * displayed normally. Without a matching key only its AES-GCM ciphertext is
 * returned to the browser. The temporary encrypted key is never exposed.
 */
function ui_text_value(string $plainText): string
{
    static $cache = [];

    // Landing and login pages remain readable before an access session exists.
    if (!is_logged_in()) {
        return $plainText;
    }

    if (array_key_exists($plainText, $cache)) {
        return $cache[$plainText];
    }

    $encrypted = crypto_service()->encrypt(['ui_text' => $plainText]);

    if (can_decrypt()) {
        $decrypted = crypto_service()->decrypt($encrypted, $_SESSION['private_key_pem']);
        $value = $decrypted['ui_text'] ?? null;
        if (!is_string($value)) {
            throw new RuntimeException('Label dashboard tidak dapat didekripsi.');
        }
    } else {
        $value = $encrypted['ciphertext'];
    }

    $cache[$plainText] = $value;
    return $value;
}

/** Safe HTML text node: ciphertext mode is visually styled as encoded text. */
function ui_text(string $plainText): string
{
    $value = e(ui_text_value($plainText));
    return (!is_logged_in() || can_decrypt()) ? $value : '<span class="cipher-ui-text">' . $value . '</span>';
}

/** Safe HTML attribute value, including the browser tab title. */
function ui_attr(string $plainText): string
{
    return e(ui_text_value($plainText));
}
