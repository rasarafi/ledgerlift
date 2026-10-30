<?php

use App\Support\LegacyCrypt;

/*
 * libraries/Crypt.php + Cryptor.php: invoice PDF passwords are stored as base64(iv . aes-256-ctr),
 * keyed by sha256(ENCRYPTION_KEY). The IV is random, so only decryption is comparable.
 */

/** The compose stack's TEST-ONLY legacy key; it carries no `base64:` prefix, so legacy uses the text itself. */
const LEGACY_CRYPT_TEST_KEY = 'bGVkZ2VybGlmdC10ZXN0LW9ubHktbGVnYWN5LWtleSE=';

it('decrypts a password that the legacy container encrypted', function () {
    // Cryptor::Encrypt("s3crét pdf", getenv('ENCRYPTION_KEY')) run in the legacy container.
    expect((new LegacyCrypt(LEGACY_CRYPT_TEST_KEY))->decode('pwm1HZC9un4dC33Ok235srDrtKheIxdGDiLb'))->toBe('s3crét pdf');
});

it('encrypts with a fresh IV each time, and decrypts what it encrypted', function () {
    $crypt = new LegacyCrypt(LEGACY_CRYPT_TEST_KEY);
    $first = $crypt->encode('open sesame');
    $second = $crypt->encode('open sesame');

    expect($first)->not->toBe($second)
        ->and(strlen((string) base64_decode($first)))->toBe(16 + strlen('open sesame'))
        ->and($crypt->decode($first))->toBe('open sesame')
        ->and($crypt->decode($second))->toBe('open sesame');
});

it('decodes a base64: key first, as Crypt::getEncryptionKey() does', function () {
    $plain = new LegacyCrypt('ledgerlift-key');
    $prefixed = new LegacyCrypt('base64:'.base64_encode('ledgerlift-key'));

    expect($prefixed->decode($plain->encode('x')))->toBe('x');
});

it('stores an empty password as NULL and reads NULL back as empty', function () {
    $crypt = new LegacyCrypt(LEGACY_CRYPT_TEST_KEY);

    expect($crypt->encryptPassword(null))->toBeNull()
        ->and($crypt->encryptPassword(''))->toBeNull()
        ->and($crypt->decryptPassword(null))->toBe('')
        ->and($crypt->decryptPassword(''))->toBe('')
        ->and($crypt->decryptPassword((string) $crypt->encryptPassword('pdf-pass')))->toBe('pdf-pass');
});

it('returns a password stored before encryption as it is when it cannot be decrypted', function () {
    // Shorter than the IV once base64-decoded: Cryptor::decryptString() throws, the trait hands back the raw value.
    expect((new LegacyCrypt(LEGACY_CRYPT_TEST_KEY))->decryptPassword('abc'))->toBe('abc');
});
