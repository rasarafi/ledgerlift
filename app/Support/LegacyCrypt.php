<?php

namespace App\Support;

use RuntimeException;

/**
 * libraries/Crypt.php encode()/decode() over libraries/Cryptor.php, with the
 * Password_Encryption_Trait wrappers Mdl_invoices uses for `invoice_password`: aes-256-ctr keyed
 * by sha256(ENCRYPTION_KEY), stored as base64(iv . ciphertext). The IV is random, so the same
 * password never encrypts to the same text twice; both apps share the key and read each other's.
 */
final class LegacyCrypt
{
    private const CIPHER = 'aes-256-ctr';

    private const IV_BYTES = 16;

    public function __construct(private readonly string $key) {}

    /** Password_Encryption_Trait::encrypt_password(): an empty password is stored as NULL. */
    public function encryptPassword(?string $password): ?string
    {
        return $password === null || $password === '' ? null : $this->encode($password);
    }

    /**
     * Password_Encryption_Trait::decrypt_password(): a value that does not decrypt (stored before
     * encryption existed) comes back as it is.
     */
    public function decryptPassword(?string $password): string
    {
        if ($password === null || $password === '') {
            return '';
        }

        try {
            return $this->decode($password);
        } catch (RuntimeException) {
            return $password;
        }
    }

    /** Cryptor::encryptString() */
    public function encode(string $data): string
    {
        $iv = random_bytes(self::IV_BYTES);
        $encrypted = openssl_encrypt($data, self::CIPHER, $this->keyHash(), OPENSSL_RAW_DATA, $iv);

        if ($encrypted === false) {
            throw new RuntimeException('Encryption failed: '.openssl_error_string());
        }

        return base64_encode($iv.$encrypted);
    }

    /** Crypt::decode() and Cryptor::decryptString(); throws where Cryptor throws. */
    public function decode(string $data): string
    {
        if ($data === '') {
            return '';
        }

        $raw = (string) base64_decode($data);

        if (strlen($raw) < self::IV_BYTES) {
            throw new RuntimeException('Data length '.strlen($raw).' is less than iv length '.self::IV_BYTES);
        }

        $decrypted = openssl_decrypt(substr($raw, self::IV_BYTES), self::CIPHER, $this->keyHash(), OPENSSL_RAW_DATA, substr($raw, 0, self::IV_BYTES));

        if ($decrypted === false) {
            throw new RuntimeException('Decryption failed: '.openssl_error_string());
        }

        return $decrypted;
    }

    /** Crypt::getEncryptionKey() (a `base64:` key is decoded first), hashed as Cryptor does. */
    private function keyHash(): string
    {
        $key = preg_match('/^base64:(.*)$/', $this->key, $matches) ? (string) base64_decode($matches[1]) : $this->key;

        return hash('sha256', $key, true);
    }
}
