<?php

/**
 * Hybrid encryption service.
 * - AES-256-GCM encrypts the title and body as one JSON payload.
 * - RSA-2048 OAEP wraps the random AES key.
 */
final class CryptoService
{
    private const CIPHER = 'aes-256-gcm';
    private const AAD = 'secure-content-admin:v1';
    private const IV_LENGTH = 12;
    private const TAG_LENGTH = 16;

    private string $publicKeyPath;
    private ?string $privateKeyPassphrase;

    public function __construct(string $publicKeyPath, ?string $privateKeyPassphrase)
    {
        $this->publicKeyPath = $publicKeyPath;
        $this->privateKeyPassphrase = $privateKeyPassphrase;
    }

    /**
     * Check whether a submitted PEM private key is the pair of the configured public key.
     * The private key file itself is not read here; the key entered on the login page is checked.
     */
    public function privateKeyMatchesPublicKey(string $privateKeyPem): bool
    {
        if (trim($privateKeyPem) === '' || !is_file($this->publicKeyPath)) {
            return false;
        }

        $privateKey = @openssl_pkey_get_private(
            $privateKeyPem,
            $this->privateKeyPassphrase ?? ''
        );
        if ($privateKey === false) {
            return false;
        }

        $publicKey = $this->loadPublicKey();
        $privateDetails = openssl_pkey_get_details($privateKey);
        $publicDetails = openssl_pkey_get_details($publicKey);

        if (!is_array($privateDetails) || !is_array($publicDetails)) {
            return false;
        }

        $privateRsa = $privateDetails['rsa'] ?? [];
        $publicRsa = $publicDetails['rsa'] ?? [];
        if (!isset($privateRsa['n'], $privateRsa['e'], $publicRsa['n'], $publicRsa['e'])) {
            return false;
        }

        return hash_equals($publicRsa['n'], $privateRsa['n'])
            && hash_equals($publicRsa['e'], $privateRsa['e']);
    }

    /** @return array{encrypted_key:string,iv:string,auth_tag:string,ciphertext:string,crypto_version:string} */
    public function encrypt(array $content): array
    {
        $publicKey = $this->loadPublicKey();
        $aesKey = random_bytes(32);
        $iv = random_bytes(self::IV_LENGTH);
        $plaintext = json_encode($content, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $tag = '';

        $ciphertext = openssl_encrypt(
            $plaintext,
            self::CIPHER,
            $aesKey,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            self::AAD,
            self::TAG_LENGTH
        );

        if ($ciphertext === false) {
            throw new RuntimeException('Enkripsi AES gagal. Pastikan ekstensi OpenSSL aktif.');
        }

        $wrappedKey = '';
        if (!openssl_public_encrypt($aesKey, $wrappedKey, $publicKey, OPENSSL_PKCS1_OAEP_PADDING)) {
            throw new RuntimeException('Enkripsi kunci AES dengan RSA gagal.');
        }

        return [
            'encrypted_key' => base64_encode($wrappedKey),
            'iv' => base64_encode($iv),
            'auth_tag' => base64_encode($tag),
            'ciphertext' => base64_encode($ciphertext),
            'crypto_version' => 'AES-256-GCM+RSA-OAEP',
        ];
    }

    /** @return array<string,mixed> */
    public function decrypt(array $record, string $privateKeyPem): array
    {
        $privateKey = @openssl_pkey_get_private(
            $privateKeyPem,
            $this->privateKeyPassphrase ?? ''
        );
        if ($privateKey === false) {
            throw new RuntimeException('Private key yang dimasukkan tidak dapat dibuka.');
        }

        $wrappedKey = $this->decodeField($record['encrypted_key'] ?? '', 'kunci terenkripsi');
        $iv = $this->decodeField($record['iv'] ?? '', 'IV');
        $tag = $this->decodeField($record['auth_tag'] ?? '', 'authentication tag');
        $ciphertext = $this->decodeField($record['ciphertext'] ?? '', 'ciphertext');

        $aesKey = '';
        if (!openssl_private_decrypt($wrappedKey, $aesKey, $privateKey, OPENSSL_PKCS1_OAEP_PADDING)) {
            throw new RuntimeException('Kunci AES tidak dapat dibuka. Private key tidak cocok.');
        }

        $plaintext = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            $aesKey,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            self::AAD
        );

        if ($plaintext === false) {
            throw new RuntimeException('Dekripsi gagal atau data telah berubah.');
        }

        $decoded = json_decode($plaintext, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) {
            throw new RuntimeException('Format konten hasil dekripsi tidak valid.');
        }

        return $decoded;
    }

    private function loadPublicKey()
    {
        if (!is_file($this->publicKeyPath)) {
            throw new RuntimeException('Public key belum tersedia. Jalankan scripts/generate_keys.php terlebih dahulu.');
        }

        $key = openssl_pkey_get_public(file_get_contents($this->publicKeyPath));
        if ($key === false) {
            throw new RuntimeException('Public key tidak dapat dibaca.');
        }

        return $key;
    }

    private function decodeField(string $value, string $field): string
    {
        $decoded = base64_decode($value, true);
        if ($decoded === false) {
            throw new RuntimeException('Format ' . $field . ' tidak valid.');
        }

        return $decoded;
    }
}
