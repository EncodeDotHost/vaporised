<?php

declare(strict_types=1);

namespace Vaporised;

use PDO;
use Throwable;

/**
 * Stores ciphertext that the browser made. The server cannot decrypt it:
 * the key is in the share link after "#", which browsers never send.
 */
final class SecretRepository
{
    public function __construct(
        private PDO $pdo,
        private int $maxFailedAttempts
    ) {
    }

    /**
     * @param string $ciphertext Opaque bytes from the browser.
     * @return array{share_token: string, delete_token: string}
     */
    public function create(string $ciphertext, string $passphrase, int $ttlSeconds, int $views): array
    {
        $shareToken = Crypto::randomToken();
        $deleteToken = Crypto::randomToken();

        $stmt = $this->pdo->prepare(
            'INSERT INTO secrets
                (id_hash, delete_hash, ciphertext, passphrase_hash, views_remaining, failed_attempts, expires_at)
             VALUES (?, ?, ?, ?, ?, 0, UTC_TIMESTAMP() + INTERVAL ? SECOND)'
        );
        $stmt->execute([
            Crypto::hashToken($shareToken),
            Crypto::hashToken($deleteToken),
            $ciphertext,
            $passphrase === '' ? null : Crypto::hashPassphrase($passphrase),
            $views,
            $ttlSeconds,
        ]);

        return ['share_token' => $shareToken, 'delete_token' => $deleteToken];
    }

    /** @return array{has_passphrase: bool}|null Null if the secret is not available. */
    public function find(string $shareToken): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT passphrase_hash IS NOT NULL AS has_passphrase
             FROM secrets WHERE id_hash = ? AND expires_at > UTC_TIMESTAMP()'
        );
        $stmt->execute([Crypto::hashToken($shareToken)]);
        $row = $stmt->fetch();

        return $row === false ? null : ['has_passphrase' => (bool) $row['has_passphrase']];
    }

    /**
     * Check the passphrase, give out the ciphertext, and use one view. The row
     * lock makes sure that simultaneous requests cannot exceed the view count.
     *
     * @return array{status: 'ok', ciphertext: string, views_remaining: int}|array{status: 'gone'}|array{status: 'bad_passphrase'}
     */
    public function reveal(string $shareToken, string $passphrase): array
    {
        $idHash = Crypto::hashToken($shareToken);
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'SELECT ciphertext, passphrase_hash, views_remaining, failed_attempts,
                        (expires_at > UTC_TIMESTAMP()) AS live
                 FROM secrets WHERE id_hash = ? FOR UPDATE'
            );
            $stmt->execute([$idHash]);
            $row = $stmt->fetch();

            if ($row === false) {
                $this->pdo->commit();
                return ['status' => 'gone'];
            }
            if (!$row['live'] || (int) $row['views_remaining'] < 1) {
                $this->deleteRow($idHash);
                $this->pdo->commit();
                return ['status' => 'gone'];
            }

            if ($row['passphrase_hash'] !== null && !Crypto::verifyPassphrase($row['passphrase_hash'], $passphrase)) {
                if ((int) $row['failed_attempts'] + 1 >= $this->maxFailedAttempts) {
                    $this->deleteRow($idHash);
                    $this->pdo->commit();
                    return ['status' => 'gone'];
                }
                $this->pdo->prepare('UPDATE secrets SET failed_attempts = failed_attempts + 1 WHERE id_hash = ?')
                    ->execute([$idHash]);
                $this->pdo->commit();
                return ['status' => 'bad_passphrase'];
            }

            $viewsRemaining = (int) $row['views_remaining'] - 1;
            if ($viewsRemaining < 1) {
                $this->deleteRow($idHash);
            } else {
                $this->pdo->prepare('UPDATE secrets SET views_remaining = ? WHERE id_hash = ?')
                    ->execute([$viewsRemaining, $idHash]);
            }
            $this->pdo->commit();

            return ['status' => 'ok', 'ciphertext' => $row['ciphertext'], 'views_remaining' => $viewsRemaining];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function existsByDeleteToken(string $deleteToken): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM secrets WHERE delete_hash = ? AND expires_at > UTC_TIMESTAMP()'
        );
        $stmt->execute([Crypto::hashToken($deleteToken)]);

        return $stmt->fetch() !== false;
    }

    public function deleteByDeleteToken(string $deleteToken): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM secrets WHERE delete_hash = ?');
        $stmt->execute([Crypto::hashToken($deleteToken)]);

        return $stmt->rowCount() > 0;
    }

    /** Delete all expired secrets. @return int Number of deleted rows. */
    public function purge(): int
    {
        return (int) $this->pdo->exec('DELETE FROM secrets WHERE expires_at <= UTC_TIMESTAMP()');
    }

    private function deleteRow(string $idHash): void
    {
        $this->pdo->prepare('DELETE FROM secrets WHERE id_hash = ?')->execute([$idHash]);
    }
}
