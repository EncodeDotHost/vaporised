<?php

declare(strict_types=1);

namespace Vaporised;

use PDO;
use Throwable;

final class SecretRepository
{
    public function __construct(
        private PDO $pdo,
        private Crypto $crypto,
        private int $maxFailedAttempts
    ) {
    }

    /** @return array{share_token: string, delete_token: string} */
    public function create(string $plaintext, string $passphrase, int $ttlSeconds, int $views): array
    {
        $shareToken = Crypto::randomToken();
        $deleteToken = Crypto::randomToken();
        $idHash = Crypto::hashToken($shareToken);
        $box = $this->crypto->encrypt($plaintext, $passphrase, $idHash);

        $stmt = $this->pdo->prepare(
            'INSERT INTO secrets
                (id_hash, delete_hash, salt, nonce, ciphertext, has_passphrase, views_remaining, failed_attempts, expires_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 0, UTC_TIMESTAMP() + INTERVAL ? SECOND)'
        );
        $stmt->execute([
            $idHash,
            Crypto::hashToken($deleteToken),
            $box['salt'],
            $box['nonce'],
            $box['ciphertext'],
            $passphrase === '' ? 0 : 1,
            $views,
            $ttlSeconds,
        ]);

        return ['share_token' => $shareToken, 'delete_token' => $deleteToken];
    }

    /** @return array{has_passphrase: bool}|null Null if the secret is not available. */
    public function find(string $shareToken): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT has_passphrase FROM secrets WHERE id_hash = ? AND expires_at > UTC_TIMESTAMP()'
        );
        $stmt->execute([Crypto::hashToken($shareToken)]);
        $row = $stmt->fetch();

        return $row === false ? null : ['has_passphrase' => (bool) $row['has_passphrase']];
    }

    /**
     * Decrypt the secret and use one view. The row lock makes sure that
     * simultaneous requests cannot exceed the view count.
     *
     * @return array{status: 'ok', secret: string, views_remaining: int}|array{status: 'gone'}|array{status: 'bad_passphrase'}
     */
    public function reveal(string $shareToken, string $passphrase): array
    {
        $idHash = Crypto::hashToken($shareToken);
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'SELECT salt, nonce, ciphertext, has_passphrase, views_remaining, failed_attempts,
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

            $secret = $this->crypto->decrypt($row['ciphertext'], $row['nonce'], $row['salt'], $passphrase, $idHash);

            if ($secret === null) {
                if (!$row['has_passphrase']) {
                    // Not a passphrase error: the data or the master key changed.
                    error_log('vaporised: a secret failed authentication');
                    $this->pdo->commit();
                    return ['status' => 'gone'];
                }
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

            return ['status' => 'ok', 'secret' => $secret, 'views_remaining' => $viewsRemaining];
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
