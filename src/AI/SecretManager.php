<?php

declare(strict_types=1);

namespace AIEA\AI;

use RuntimeException;

final class SecretManager
{
    private const OPTION = 'aiea_provider_secret';
    private const CIPHER = 'aes-256-gcm';

    public function store(string $secret): void
    {
        $secret = trim($secret);
        if ($secret === '') {
            return;
        }

        $key = $this->key();
        $iv = random_bytes(openssl_cipher_iv_length(self::CIPHER));
        $tag = '';
        $cipherText = openssl_encrypt($secret, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv, $tag);
        if (!is_string($cipherText)) {
            throw new RuntimeException('Unable to encrypt provider secret.');
        }

        update_option(
            self::OPTION,
            [
                'version' => 1,
                'ciphertext' => base64_encode($cipherText),
                'iv' => base64_encode($iv),
                'tag' => base64_encode($tag),
            ],
            false,
        );
    }

    public function retrieve(): ?string
    {
        if (defined('AIEA_API_KEY') && is_string(AIEA_API_KEY) && AIEA_API_KEY !== '') {
            return AIEA_API_KEY;
        }

        $stored = get_option(self::OPTION, []);
        if (!is_array($stored) || empty($stored['ciphertext']) || empty($stored['iv']) || empty($stored['tag'])) {
            return null;
        }

        $plainText = openssl_decrypt(
            (string) base64_decode((string) $stored['ciphertext'], true),
            self::CIPHER,
            $this->key(),
            OPENSSL_RAW_DATA,
            (string) base64_decode((string) $stored['iv'], true),
            (string) base64_decode((string) $stored['tag'], true),
        );

        return is_string($plainText) && $plainText !== '' ? $plainText : null;
    }

    public function hasSecret(): bool
    {
        return $this->retrieve() !== null;
    }

    public function forget(): void
    {
        delete_option(self::OPTION);
    }

    public function masked(): ?string
    {
        $secret = $this->retrieve();
        if ($secret === null) {
            return null;
        }

        return strlen($secret) <= 8 ? '••••••••' : substr($secret, 0, 3) . '••••' . substr($secret, -4);
    }

    private function key(): string
    {
        $seed = defined('AIEA_ENCRYPTION_KEY') && is_string(AIEA_ENCRYPTION_KEY)
            ? AIEA_ENCRYPTION_KEY
            : wp_salt('auth') . '|' . wp_salt('secure_auth');

        return hash('sha256', $seed, true);
    }
}
