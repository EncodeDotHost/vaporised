<?php

declare(strict_types=1);

// Dependency-free tests. Run: php tests/run.php
// The database tests run only if VAPORISED_CONFIG points to a config file
// with a test database that has the schema from sql/schema.sql.

use Vaporised\Config;
use Vaporised\Crypto;
use Vaporised\Database;
use Vaporised\ProofOfWork;
use Vaporised\RateLimiter;
use Vaporised\SecretRepository;

require dirname(__DIR__) . '/src/bootstrap.php';

$failures = 0;
$count = 0;

function check(string $name, bool $condition): void
{
    global $failures, $count;
    $count++;
    if (!$condition) {
        $failures++;
        echo "FAIL: {$name}\n";
    }
}

function solve(ProofOfWork $pow, string $challenge): string
{
    for ($nonce = 0; ; $nonce++) {
        if ($pow->check($challenge, (string) $nonce)) {
            return (string) $nonce;
        }
    }
}

// --- Crypto ---------------------------------------------------------------
// The browser encrypts secrets (see tests/crypto.js). The server only handles
// tokens, base64url, and passphrase hashes.

check('token is 43 base64url characters', preg_match('/^[A-Za-z0-9_-]{43}$/', Crypto::randomToken()) === 1);
check('token hash is 32 bytes', strlen(Crypto::hashToken('abc')) === 32);
check('base64url round trip', Crypto::b64uDecode(Crypto::b64u("\x00\xff\x10 bytes")) === "\x00\xff\x10 bytes");
check('base64url matches the browser', Crypto::b64u("\x01\x02\x03\xff\xef") === 'AQID_-8');
check('standard base64 is refused', Crypto::b64uDecode('a+b/') === null);
check('padding is refused', Crypto::b64uDecode('AQ==') === null);
check('empty base64url decodes to empty', Crypto::b64uDecode('') === '');

$hash = Crypto::hashPassphrase('correct horse');
check('passphrase hash is not the passphrase', !str_contains($hash, 'correct horse'));
check('passphrase hash fits the column', strlen($hash) <= 128);
check('correct passphrase verifies', Crypto::verifyPassphrase($hash, 'correct horse'));
check('incorrect passphrase fails', !Crypto::verifyPassphrase($hash, 'wrong'));
check('empty passphrase fails', !Crypto::verifyPassphrase($hash, ''));

// --- Proof-of-work (no database) --------------------------------------------

check('leading zero bits', ProofOfWork::leadingZeroBits("\x00\x1f") === 11);

$pow = new ProofOfWork(random_bytes(32), 8, 600);
$issued = $pow->issue();
$nonce = solve($pow, $issued['challenge']);
check('solved challenge passes', $pow->check($issued['challenge'], $nonce));
check('non-numeric nonce fails', !$pow->check($issued['challenge'], 'abc'));
check('challenge from a different key fails', !(new ProofOfWork(random_bytes(32), 8, 600))->check($issued['challenge'], $nonce));

$forged = preg_replace('/\.\d{10}\./', '.9999999999.', $issued['challenge']);
check('changed expiry time fails', !$pow->check($forged, $nonce));

$expired = (new ProofOfWork(random_bytes(32), 0, -10));
$old = $expired->issue();
check('expired challenge fails', !$expired->check($old['challenge'], '0'));

// --- Database ---------------------------------------------------------------

if (getenv('VAPORISED_CONFIG') === false) {
    echo "Database tests skipped (VAPORISED_CONFIG is not set).\n";
} else {
    $config = Config::load();
    $pdo = Database::connect($config);
    $repo = new SecretRepository($pdo, 3);
    $blob = random_bytes(80); // Stands in for a browser-made ciphertext.

    $tokens = $repo->create($blob, '', 3600, 2);
    check('find returns the secret', $repo->find($tokens['share_token']) === ['has_passphrase' => false]);
    $first = $repo->reveal($tokens['share_token'], '');
    check('first view', $first['status'] === 'ok' && $first['ciphertext'] === $blob && $first['views_remaining'] === 1);
    check('ciphertext is stored as given', $first['ciphertext'] === $blob);
    $second = $repo->reveal($tokens['share_token'], '');
    check('second view', $second['status'] === 'ok' && $second['views_remaining'] === 0);
    check('third view is gone', $repo->reveal($tokens['share_token'], '')['status'] === 'gone');
    check('find after last view', $repo->find($tokens['share_token']) === null);

    $tokens = $repo->create($blob, 'pass', 3600, 1);
    check('find reports the passphrase', $repo->find($tokens['share_token']) === ['has_passphrase' => true]);
    check('database holds no passphrase', $pdo->query("SELECT COUNT(*) FROM secrets WHERE passphrase_hash = 'pass'")->fetchColumn() == 0);
    check('incorrect passphrase 1', $repo->reveal($tokens['share_token'], 'bad')['status'] === 'bad_passphrase');
    check('correct passphrase still works', $repo->reveal($tokens['share_token'], 'pass')['status'] === 'ok');

    $tokens = $repo->create($blob, 'pass', 3600, 1);
    $repo->reveal($tokens['share_token'], 'bad');
    $repo->reveal($tokens['share_token'], 'bad');
    check('deleted at the attempt limit', $repo->reveal($tokens['share_token'], 'bad')['status'] === 'gone');
    check('correct passphrase after deletion', $repo->reveal($tokens['share_token'], 'pass')['status'] === 'gone');

    $tokens = $repo->create($blob, '', 3600, 5);
    check('delete token exists', $repo->existsByDeleteToken($tokens['delete_token']));
    check('share token is not a delete token', !$repo->deleteByDeleteToken($tokens['share_token']));
    check('delete works', $repo->deleteByDeleteToken($tokens['delete_token']));
    check('deleted secret is gone', $repo->reveal($tokens['share_token'], '')['status'] === 'gone');
    check('second delete fails', !$repo->deleteByDeleteToken($tokens['delete_token']));

    $tokens = $repo->create($blob, '', 3600, 5);
    $pdo->prepare('UPDATE secrets SET expires_at = UTC_TIMESTAMP() - INTERVAL 1 SECOND WHERE id_hash = ?')
        ->execute([Crypto::hashToken($tokens['share_token'])]);
    check('expired secret is not found', $repo->find($tokens['share_token']) === null);
    check('expired delete token is not found', !$repo->existsByDeleteToken($tokens['delete_token']));
    check('purge deletes the expired secret', $repo->purge() >= 1);
    check('expired secret is gone', $repo->reveal($tokens['share_token'], '')['status'] === 'gone');

    $limiter = new RateLimiter($pdo, random_bytes(32), 3600, ['create' => 2, 'reveal' => 1]);
    check('rate limit hit 1', $limiter->hit('create', '203.0.113.9'));
    check('rate limit hit 2', $limiter->hit('create', '203.0.113.9'));
    check('rate limit hit 3 is refused', !$limiter->hit('create', '203.0.113.9'));
    check('other action has its own count', $limiter->hit('reveal', '203.0.113.9'));
    check('other address has its own count', $limiter->hit('create', '203.0.113.10'));
    check('table holds no IP text', $pdo->query("SELECT COUNT(*) FROM rate_limits WHERE key_hash LIKE '%203.0.113%'")->fetchColumn() == 0);
    $pdo->exec('UPDATE rate_limits SET window_start = UTC_TIMESTAMP() - INTERVAL 2 HOUR');
    check('new window resets the count', $limiter->hit('create', '203.0.113.9'));
    $pdo->exec('UPDATE rate_limits SET window_start = UTC_TIMESTAMP() - INTERVAL 2 HOUR');
    check('purge deletes ended windows', $limiter->purge() >= 3);

    $pow = new ProofOfWork($config->key('pow_key'), 8, 600);
    $issued = $pow->issue();
    $nonce = solve($pow, $issued['challenge']);
    check('proof-of-work verifies', $pow->verify($pdo, $issued['challenge'], $nonce));
    check('proof-of-work works only one time', !$pow->verify($pdo, $issued['challenge'], $nonce));
    $pdo->exec('UPDATE pow_used SET expires_at = UTC_TIMESTAMP() - INTERVAL 1 SECOND');
    check('purge deletes expired challenges', $pow->purge($pdo) >= 1);
}

echo $failures === 0 ? "OK: {$count} checks passed\n" : "{$failures} of {$count} checks FAILED\n";
exit($failures === 0 ? 0 : 1);
