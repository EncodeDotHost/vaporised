<?php

declare(strict_types=1);

namespace Vaporised;

use SodiumException;

/**
 * Server-side helpers. The server never holds an encryption key: the browser
 * encrypts each secret, and the key travels in the share link after "#".
 */
final class Crypto
{
    public static function b64u(string $bytes): string
    {
        return sodium_bin2base64($bytes, SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
    }

    /** @return string|null Null if the text is not base64url. */
    public static function b64uDecode(string $text): ?string
    {
        if (!preg_match('/^[A-Za-z0-9_-]*$/', $text)) {
            return null;
        }
        try {
            return sodium_base642bin($text, SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
        } catch (SodiumException) {
            return null;
        }
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

    /** Argon2id hash of a passphrase, so the server can count incorrect attempts. */
    public static function hashPassphrase(string $passphrase): string
    {
        return sodium_crypto_pwhash_str(
            $passphrase,
            SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE,
            SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE
        );
    }

    public static function verifyPassphrase(string $hash, string $passphrase): bool
    {
        // libsodium warns on an empty password, and a stored hash is never of an empty one.
        return $passphrase !== '' && sodium_crypto_pwhash_str_verify($hash, $passphrase);
    }
}
