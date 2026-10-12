# CLAUDE.md

Guidance for Claude Code when it works in this repository.

## What this is

Vaporised (www.vaporised.co.uk) is a self-destructing secret-sharing web app. Plain PHP 8.3+ with
`sodium`, `pdo_mysql`, and `mbstring`. No Composer, no framework, no build step, no Docker.

## Commands

```sh
php tests/run.php                                        # PHP tests, no database
node tests/crypto.js                                     # Browser crypto tests (Node runs the same file)
VAPORISED_CONFIG=/path/to/test-config.php php tests/run.php   # Adds the database tests
VAPORISED_CONFIG=/path/to/dev-config.php php -S localhost:8000 -t public public/index.php
php -l path/to/file.php                                  # Syntax check
```

A config file is a PHP file that returns an array. Start from `config/config.example.php`.
The database tests need a database with `sql/schema.sql` imported. MariaDB does not start at boot on
the dev machine; ask before you need it.

## Architecture

- `public/index.php` sets security headers and calls `Vaporised\Controller::dispatch()`.
- `src/Controller.php` routes by path and method. HTML pages render through `templates/layout.php`.
  `POST /secrets` and `POST /s/{token}` return JSON for the page JavaScript.
- `src/SecretRepository.php` is the only code that touches the `secrets` table.
- `src/Crypto.php` has tokens, base64url, and Argon2id passphrase hashing. It holds no encryption key.
- `src/RateLimiter.php` and `src/ProofOfWork.php` throttle creation and reveal.
- `bin/purge.php` is the cron job that deletes expired rows.
- `public/assets/crypto.js` encrypts and decrypts in the browser. `public/assets/app.js` drives the pages.

## Encryption model (do not weaken)

The server must never be able to decrypt a secret.

- The browser makes a random 256-bit link key and puts it in the share link after `#`.
  Browsers never send the fragment, so the server, database, and access logs never see it.
- The browser derives an AES-256-GCM key with HKDF-SHA256 from the link key, a salt, and (optionally)
  PBKDF2-SHA256 of the passphrase. The blob format is in the comment at the top of `crypto.js`.
- The server stores only the blob. `Controller::CIPHERTEXT_OVERHEAD` must match the blob layout in `crypto.js`.
- A passphrase is both part of the browser key and Argon2id-hashed on the server, so the server can
  count incorrect attempts and delete the secret at the limit.
- Do not add a server-side key, do not log request bodies, and do not move the key out of the fragment.
- If you change the blob format, bump the version byte and update both `crypto.js` and `tests/crypto.js`.

## Conventions

- `declare(strict_types=1)` and `final` classes. Prepared statements only. UTC everywhere (`UTC_TIMESTAMP()`).
- Templates are plain PHP. Escape every output with `e()`. Templates get only the variables the controller passes.
- The database never holds a token, an IP address, or a passphrase. It holds hashes.
- No cookies, no browser storage, no third-party assets. Keep the CSP in `public/index.php` at `default-src 'self'`.
- JavaScript is ES5-style syntax with `async` functions and no dependencies. Both JS files must load in Node for tests.
- Every folder except `public/` has a `.htaccess` with `Require all denied`. Keep it when you add a folder.
- Public text uses short, plain sentences (Simplified Technical English). Match the tone of `templates/help.php`.
- When behaviour changes, update `README.md`, `templates/help.php`, and `templates/privacy.php` together.

## Deployment

cPanel, Git deploy from GitHub with a read-only deploy key, document root is the repository root.
The config file lives one level above the document root at `/home/vaporised/vaporised-config.php`.
`sql/schema.sql` drops and recreates the `secrets` table, so a schema change only needs an edit there and a
README note. Secrets live at most 7 days, and the server cannot migrate rows it cannot decrypt.
