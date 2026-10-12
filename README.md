# Vaporised

Vaporised is a small web app for sending a password or other secret by a link that deletes itself.
The browser encrypts the secret before it leaves your device. The server stores only ciphertext and
never has the key, so the operator cannot read your secrets.

The public instance is at [www.vaporised.co.uk](https://www.vaporised.co.uk). You can also host your own.

## Features

- Sends a secret as a link. The link works for a time limit (up to 7 days) and a number of views that the sender sets.
- Encrypts in the browser with AES-256-GCM. The key is the part of the link after `#`, which browsers never send to a server.
- Optional passphrase. It adds to the encryption key, and the server deletes the secret after too many incorrect attempts.
- Delete link, so the sender can remove a secret early.
- No accounts, no cookies, no analytics, no third-party assets, no stored IP addresses.
- Plain PHP and vanilla JavaScript. No framework, no Composer packages, no build step.

## How it works

1. The browser makes a random 256-bit link key.
2. The browser derives an AES-256-GCM key with HKDF-SHA256 from the link key, a random salt,
   and (with a passphrase) PBKDF2-SHA256 of the passphrase.
3. The browser sends only the ciphertext to the server. The server stores it and returns a share link.
4. The browser adds `#` and the link key to the share link. The sender copies the full link.
5. When the recipient opens the link, the browser reads the key from the fragment, fetches the ciphertext,
   and decrypts it locally. Each fetch uses one view.

The server stores a SHA-256 hash of each link token, not the token. With a passphrase, it stores an Argon2id
hash of the passphrase, not the passphrase. The encryption code is in `public/assets/crypto.js`, and
`tests/crypto.js` runs the same file in Node.

### What this does not protect against

- The server serves the JavaScript. An operator or attacker who controls the server could serve altered code.
  Use an instance that you trust, or host your own.
- The full link, including the key, is in the browser history and in any message where it was sent.
- Anyone with the full link (and the passphrase, if set) can read the secret while it is live.

## Requirements

- PHP 8.3 or later with the `sodium`, `pdo_mysql`, and `mbstring` extensions.
- MySQL 8.4 or MariaDB 10.6 or later.
- Apache with `mod_rewrite` and `.htaccess` permitted, or another web server with equivalent rules.
- HTTPS. The browser crypto API needs a secure context, and the app forces HTTPS except on `localhost`.
- Node (any current version) only to run the JavaScript tests.

## Install

These steps work on any host. See [Install on cPanel](#install-on-cpanel) for that control panel.

1. Clone the repository so that the repository root is the document root of the site.
   The root `.htaccess` sends all requests to `public/` and blocks every other folder.
2. Create a MySQL database and a user with all privileges on it, and import `sql/schema.sql`.
3. Copy `config/config.example.php` to `vaporised-config.php` one level above the document root,
   so that the web server cannot serve it. Set the file permissions to `600`.
4. In that file, set the database values and `base_url` (the public address of the site).
5. Generate two different keys and put them in `rate_limit_key` and `pow_key`:

   ```sh
   php -r 'echo base64_encode(random_bytes(32)), PHP_EOL;'
   ```

6. Add a cron job that runs each 5 minutes to delete expired rows:

   ```sh
   */5 * * * * php /path/to/document-root/bin/purge.php
   ```

7. Open the site, save a test secret, and open the share link in another browser.

The app reads the config file from `../vaporised-config.php` relative to the repository root.
To use another location, set the `VAPORISED_CONFIG` environment variable to the full path.

### Install on cPanel

1. In **Git Version Control**, clone the repository into `public_html`. Use a read-only deploy key (see below).
2. In **MySQL Databases**, create the database and user. In **phpMyAdmin**, import `sql/schema.sql`.
3. In **File Manager**, copy the config file to your home directory (one level above `public_html`), set its values,
   and set its permissions to `600`.
4. In **Cron Jobs**, add the purge job. Use the PHP binary for your PHP version, for example
   `/opt/cpanel/ea-php83/root/usr/bin/php`.
5. In **MultiPHP Manager**, set the domain to PHP 8.3 or later.
6. In **Raw Access**, disable "Archive Logs". Apache access logs record IP addresses and link paths
   (never the key, which is after `#`).

### Deploy keys

A deploy key lets the server pull from GitHub without a password and without write access.

1. On the server, generate a key without a passphrase:

   ```sh
   ssh-keygen -t ed25519 -f ~/.ssh/vaporised_deploy -N "" -C "vaporised deploy"
   ```

2. Add this to `~/.ssh/config` on the server:

   ```
   Host github-vaporised
       HostName github.com
       User git
       IdentityFile ~/.ssh/vaporised_deploy
       IdentitiesOnly yes
   ```

3. In GitHub, go to the repository **Settings > Deploy keys** and add the contents of `~/.ssh/vaporised_deploy.pub`.
   Do not give write access.
4. Clone with `git@github-vaporised:OWNER/vaporised.git`, where `OWNER` is the GitHub account that holds your fork.
5. To deploy a change, run `git pull` on the server, or use **Pull or Deploy > Update from Remote** in cPanel.

## Configuration

All values are in the config file. Each key has a comment in `config/config.example.php`.

| Key | Purpose |
|---|---|
| `base_url` | The public address of the site. Requests for any other host name redirect here (301), and it is the canonical URL in the meta tags |
| `db` | PDO DSN, user, and password |
| `rate_limit_key`, `pow_key` | 32-byte keys in base64. They sign rate-limit hashes and proof-of-work challenges. They never encrypt secrets |
| `max_secret_chars` | Maximum secret length |
| `max_views` | Highest view count a sender can set |
| `max_failed_attempts` | Incorrect passphrase attempts before the app deletes the secret |
| `ttl_choices` | Time limit choices in seconds. The app ignores values above 7 days |
| `rate_limit` | Window length and maximum create and reveal requests for each hashed IP address |
| `pow` | Proof-of-work difficulty and challenge lifetime. Each added bit doubles the browser work |

## Layout

| Path | Contents |
|---|---|
| `public/` | Front controller, stylesheet, and JavaScript (`crypto.js` encrypts, `app.js` drives the pages) |
| `src/` | Application classes |
| `templates/` | HTML templates |
| `bin/purge.php` | Cron script |
| `sql/schema.sql` | Database tables |
| `config/config.example.php` | Template for the config file |
| `tests/` | `run.php` (PHP) and `crypto.js` (Node) |
| `CLAUDE.md` | Notes for AI coding assistants |

Each folder except `public/` has a `.htaccess` with `Require all denied`.

## Development

Make a dev config from the example. Set `base_url` to `http://localhost:8000`, or every request redirects to
the production address. Then:

```sh
VAPORISED_CONFIG=/path/to/dev-config.php php -S localhost:8000 -t public public/index.php
```

The built-in server does not read `.htaccess`. Use Apache to test the rewrite and deny rules.

### Tests

Without a database:

```sh
php tests/run.php
node tests/crypto.js
```

With a database: create a test database, import `sql/schema.sql`, make a test config file, and run:

```sh
VAPORISED_CONFIG=/path/to/test-config.php php tests/run.php
```

## Upgrading

Import `sql/schema.sql` again after a schema change. It drops and recreates the `secrets` table and keeps
the other tables. Secrets live at most 7 days, and the server cannot migrate rows it cannot decrypt.

Versions before v2.0.0 encrypted secrets on the server with a `master_key`. After you import the
schema, remove `master_key` from the config file.

## Security

To report a vulnerability, use **Security > Report a vulnerability** on the GitHub repository.
Do not open a public issue for a security problem.

## Licence

GNU General Public License v3.0. See `LICENSE`.
