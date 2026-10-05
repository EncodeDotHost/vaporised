<?php

declare(strict_types=1);

namespace Vaporised;

use InvalidArgumentException;

final class Crypto
{
    public function __construct(private string $masterKey)
    {
        if (strlen($masterKey) !== SODIUM_CRYPTO_GENERICHASH_KEYBYTES) {
            throw new InvalidArgumentException('Master key must be 32 bytes.');
        }
    }

    public static function b64u(string $bytes): string
    {
        return sodium_bin2base64($bytes, SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
    }

    /** A 256-bit random token for a link (43 base64url characters). */
    public static function randomToken(): string
    {
        return self::b64u(random_bytes(32));
    }

    /** The database stores only this hash of a link token. */
    public static function hashToken(string $token): string
    {
        return hash('sha256', $token, true);
    }

    /**
     * @param string $idHash Binds the ciphertext to its database row.
     * @return array{salt: string, nonce: string, ciphertext: string}
     */
    public function encrypt(string $plaintext, string $passphrase, string $idHash): array
    {
        $salt = random_bytes(SODIUM_CRYPTO_PWHASH_SALTBYTES);
        $nonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
        $key = $this->deriveKey($salt, $passphrase);
        $ciphertext = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($plaintext, $idHash, $nonce, $key);
        sodium_memzero($key);

        return ['salt' => $salt, 'nonce' => $nonce, 'ciphertext' => $ciphertext];
    }

    /** @return string|null Null if the passphrase is incorrect or the data is not authentic. */
    public function decrypt(string $ciphertext, string $nonce, string $salt, string $passphrase, string $idHash): ?string
    {
        $key = $this->deriveKey($salt, $passphrase);
        $plaintext = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt($ciphertext, $idHash, $nonce, $key);
        sodium_memzero($key);

        return $plaintext === false ? null : $plaintext;
    }

    /** Per-secret key from the master key, the salt, and the optional passphrase. */
    private function deriveKey(string $salt, string $passphrase): string
    {
        $passphraseKey = '';
        if ($passphrase !== '') {
            $passphraseKey = sodium_crypto_pwhash(
                32,
                $passphrase,
                $salt,
                SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE,
                SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE,
                SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13
            );
        }

        return sodium_crypto_generichash($salt . $passphraseKey, $this->masterKey, 32);
    }
}
