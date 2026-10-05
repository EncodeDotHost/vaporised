# vaporised

Source code for www.vaporised.co.uk. The app helps you send sensitive information through email.
You send a username by email, save the password with Vaporised, and send only the link.
The information is never all in one location.

- The app sets no cookies and stores no identifiable information.
- Secrets are encrypted at rest with XChaCha20-Poly1305.
- The sender sets a time limit (maximum 7 days) and a maximum number of views.
- An optional passphrase adds to the encryption key.
- The app deletes a secret at its last view or time limit, and keeps no archives.

## Requirements

- PHP 8.3, 8.4, or 8.5 with `sodium`, `pdo_mysql`, and `mbstring`.
- MySQL 8.4.
- Apache with `mod_rewrite` and `.htaccess` permitted.
- No Composer packages.

## Layout

The repository root is the document root. The root `.htaccess` sends all requests to `public/`.
It also blocks `.git` and all other dotfiles. Each other folder has a `Require all denied` file.

| Path | Contents |
|---|---|
| `public/` | Front controller and static assets |
| `src/` | Application classes |
| `templates/` | HTML templates |
| `bin/purge.php` | Cron script |
| `sql/schema.sql` | Database tables |
| `config/config.example.php` | Template for the config file |
| `tests/run.php` | Tests |

## Install on cPanel

1. In **Git Version Control**, clone the repository into `/home/vaporised/public_html`.
   Use a read-only deploy key (see below).
2. In **MySQL Databases**, create a database and a user, and give the user all privileges on the database.
3. In **phpMyAdmin**, import `sql/schema.sql` into the database.
4. Copy `config/config.example.php` to `/home/vaporised/vaporised-config.php`.
   This location is above the document root, so the web server cannot serve it.
5. Set the database values and `base_url` in that file.
6. Generate three different keys. Put them in `master_key`, `rate_limit_key`, and `pow_key`:

   ```sh
   php -r 'echo base64_encode(random_bytes(32)), PHP_EOL;'
   ```

7. Set the file permissions to `600`:

   ```sh
   chmod 600 /home/vaporised/vaporised-config.php
   ```

8. In **Cron Jobs**, add this job to run each 5 minutes (`*/5 * * * *`):

   ```sh
   /opt/cpanel/ea-php83/root/usr/bin/php /home/vaporised/public_html/bin/purge.php
   ```

9. In **MultiPHP Manager**, set the domain to PHP 8.3 or higher.
10. In **Raw Access**, disable "Archive Logs". Apache access logs record IP addresses and link paths.

> **Warning:** Keep a safe copy of `master_key`. If you lose or change it, all stored secrets become unreadable.

## Deploy keys

1. On the server, in **Terminal**, generate a key without a passphrase:

   ```sh
   ssh-keygen -t ed25519 -f ~/.ssh/vaporised_deploy -N "" -C "vaporised deploy"
   ```

2. Add this SSH configuration to `~/.ssh/config`:

   ```
   Host github-vaporised
       HostName github.com
       User git
       IdentityFile ~/.ssh/vaporised_deploy
       IdentitiesOnly yes
   ```

3. In GitHub, go to the repository **Settings > Deploy keys**.
   Add the contents of `~/.ssh/vaporised_deploy.pub`. Do not give write access.
4. In cPanel **Git Version Control**, use this clone URL:

   ```
   git@github-vaporised:OWNER/vaporised.git
   ```

5. To deploy a change, use **Pull or Deploy > Update from Remote**.

## Configuration

All values are in `/home/vaporised/vaporised-config.php`.

| Key | Purpose |
|---|---|
| `ttl_choices` | Time limit choices. The app ignores values above 7 days |
| `max_views` | Highest view count a sender can set |
| `max_secret_chars` | Maximum secret length |
| `max_failed_attempts` | Incorrect passphrase attempts before the app deletes the secret |
| `rate_limit` | Window length and maximum create and reveal requests for each hashed IP address |
| `pow.difficulty_bits` | Proof-of-work difficulty. Each added bit doubles the browser work |

## Tests

Tests without a database:

```sh
php tests/run.php
```

Tests with a database: create a test database, import `sql/schema.sql`, and make a test config file. Then run:

```sh
VAPORISED_CONFIG=/path/to/test-config.php php tests/run.php
```

## Local development

```sh
VAPORISED_CONFIG=/path/to/dev-config.php php -S localhost:8000 -t public public/index.php
```

The built-in server does not read `.htaccess`. Use Apache to test the rewrite and deny rules.
