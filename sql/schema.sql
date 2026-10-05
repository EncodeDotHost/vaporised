-- Vaporised database schema (MySQL 8.4). All times are UTC.

CREATE TABLE IF NOT EXISTS secrets (
    id_hash          BINARY(32)       NOT NULL,  -- SHA-256 of the share token
    delete_hash      BINARY(32)       NOT NULL,  -- SHA-256 of the delete token
    salt             BINARY(16)       NOT NULL,
    nonce            BINARY(24)       NOT NULL,
    ciphertext       MEDIUMBLOB       NOT NULL,  -- XChaCha20-Poly1305
    has_passphrase   TINYINT UNSIGNED NOT NULL,
    views_remaining  TINYINT UNSIGNED NOT NULL,
    failed_attempts  TINYINT UNSIGNED NOT NULL DEFAULT 0,
    expires_at       DATETIME         NOT NULL,
    PRIMARY KEY (id_hash),
    UNIQUE KEY delete_hash (delete_hash),
    KEY expires_at (expires_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS rate_limits (
    key_hash      BINARY(32)   NOT NULL,  -- HMAC-SHA256 of the IP address
    action        VARCHAR(16)  CHARACTER SET ascii NOT NULL,
    window_start  DATETIME     NOT NULL,
    hits          INT UNSIGNED NOT NULL,
    PRIMARY KEY (key_hash, action),
    KEY window_start (window_start)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS pow_used (
    challenge_hash  BINARY(32) NOT NULL,  -- SHA-256 of a solved challenge
    expires_at      DATETIME   NOT NULL,
    PRIMARY KEY (challenge_hash),
    KEY expires_at (expires_at)
) ENGINE=InnoDB;
