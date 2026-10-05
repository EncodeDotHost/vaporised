<?php

declare(strict_types=1);

namespace Vaporised;

use PDO;

/**
 * Stateless proof-of-work challenge. The client must find a nonce so that
 * SHA-256("challenge:nonce") starts with the configured number of zero bits.
 */
final class ProofOfWork
{
    public function __construct(
        private string $key,
        private int $difficultyBits,
        private int $ttlSeconds
    ) {
    }

    /** @return array{challenge: string, bits: int} */
    public function issue(): array
    {
        $body = Crypto::b64u(random_bytes(16)) . '.' . (time() + $this->ttlSeconds);

        return ['challenge' => $body . '.' . $this->sign($body), 'bits' => $this->difficultyBits];
    }

    /** Check the signature, the expiry time, and the work. Does not use the database. */
    public function check(string $challenge, string $nonce): bool
    {
        if (!preg_match('/^([A-Za-z0-9_-]{22}\.(\d{10}))\.([A-Za-z0-9_-]{43})$/', $challenge, $m)) {
            return false;
        }
        if (!preg_match('/^\d{1,15}$/', $nonce)) {
            return false;
        }
        if (!hash_equals($this->sign($m[1]), $m[3]) || (int) $m[2] <= time()) {
            return false;
        }

        return self::leadingZeroBits(hash('sha256', $challenge . ':' . $nonce, true)) >= $this->difficultyBits;
    }

    /** Check the solution and record the challenge, so that it works only one time. */
    public function verify(PDO $pdo, string $challenge, string $nonce): bool
    {
        if (!$this->check($challenge, $nonce)) {
            return false;
        }
        $stmt = $pdo->prepare(
            'INSERT IGNORE INTO pow_used (challenge_hash, expires_at)
             VALUES (?, UTC_TIMESTAMP() + INTERVAL ? SECOND)'
        );
        $stmt->execute([hash('sha256', $challenge, true), $this->ttlSeconds]);

        return $stmt->rowCount() === 1;
    }

    /** Delete used challenges that expired. @return int Number of deleted rows. */
    public function purge(PDO $pdo): int
    {
        return (int) $pdo->exec('DELETE FROM pow_used WHERE expires_at <= UTC_TIMESTAMP()');
    }

    public static function leadingZeroBits(string $hash): int
    {
        $bits = 0;
        for ($i = 0, $length = strlen($hash); $i < $length; $i++) {
            $byte = ord($hash[$i]);
            if ($byte === 0) {
                $bits += 8;
                continue;
            }
            for ($mask = 0x80; ($byte & $mask) === 0; $mask >>= 1) {
                $bits++;
            }
            break;
        }

        return $bits;
    }

    private function sign(string $body): string
    {
        return Crypto::b64u(hash_hmac('sha256', $body, $this->key, true));
    }
}
