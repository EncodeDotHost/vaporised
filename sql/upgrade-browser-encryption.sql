-- Upgrade from server-side encryption to browser-side encryption.
-- Run one time on a database that was made with the old schema.sql.
--
-- WARNING: this deletes all stored secrets. The old rows were encrypted with
-- the server master_key, and the new code cannot read them.

DELETE FROM secrets;

ALTER TABLE secrets
    DROP COLUMN salt,
    DROP COLUMN nonce,
    DROP COLUMN has_passphrase,
    ADD COLUMN passphrase_hash VARCHAR(128) CHARACTER SET ascii NULL AFTER ciphertext;
