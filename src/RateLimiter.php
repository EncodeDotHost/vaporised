<?php

declare(strict_types=1);

namespace Vaporised;

use PDO;

/**
 * Counts requests per keyed hash of the IP address. The table never holds
 * the IP address, and the purge deletes each row after its window ends.
 */
final class RateLimiter
{
    /** @param array<string, int> $limits Maximum hits per window for each action. */
    public function __construct(
        private PDO $pdo,
        private string $key,
        private int $windowSeconds,
        private array $limits
    ) {
    }

    /** Count one request. @return bool False if the request is over the limit. */
    public function hit(string $action, string $ip): bool
    {
        $keyHash = hash_hmac('sha256', $ip, $this->key, true);

        // hits must be assigned before window_start: it reads the old window_start.
        $stmt = $this->pdo->prepare(
            'INSERT INTO rate_limits (key_hash, action, window_start, hits)
             VALUES (?, ?, UTC_TIMESTAMP(), 1)
             ON DUPLICATE KEY UPDATE
                hits = IF(window_start <= UTC_TIMESTAMP() - INTERVAL ? SECOND, 1, hits + 1),
                window_start = IF(window_start <= UTC_TIMESTAMP() - INTERVAL ? SECOND, UTC_TIMESTAMP(), window_start)'
        );
        $stmt->execute([$keyHash, $action, $this->windowSeconds, $this->windowSeconds]);

        $stmt = $this->pdo->prepare('SELECT hits FROM rate_limits WHERE key_hash = ? AND action = ?');
        $stmt->execute([$keyHash, $action]);

        return (int) $stmt->fetchColumn() <= $this->limits[$action];
    }

    /** Delete rows whose window ended. @return int Number of deleted rows. */
    public function purge(): int
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM rate_limits WHERE window_start <= UTC_TIMESTAMP() - INTERVAL ? SECOND'
        );
        $stmt->execute([$this->windowSeconds]);

        return $stmt->rowCount();
    }
}
