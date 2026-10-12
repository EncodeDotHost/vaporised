-- Vaporised database schema (MySQL 8.4). All times are UTC.
--
-- Import this file for a new install and again after a schema change.
-- WARNING: it deletes all stored secrets. Secrets live at most 7 days, and
-- the browser holds the only key, so the server cannot migrate old rows.
-- The other tables keep their rows.

DROP TABLE IF EXISTS secrets;

CREATE TABLE secrets (
    id_hash          BINARY(32)       NOT NULL,  -- SHA-256 of the share token
    delete_hash      BINARY(32)       NOT NULL,  -- SHA-256 of the delete token
    ciphertext       MEDIUMBLOB       NOT NULL,  -- Made by the browser (AES-256-GCM). The server has no key.
    passphrase_hash  VARCHAR(128)     CHARACTER SET ascii NULL,  -- Argon2id, or NULL without a passphrase
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
