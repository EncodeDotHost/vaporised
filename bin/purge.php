<?php

declare(strict_types=1);

// Cron script: deletes expired secrets, ended rate-limit windows, and expired challenges.

use Vaporised\Config;
use Vaporised\Database;
use Vaporised\ProofOfWork;
use Vaporised\RateLimiter;
use Vaporised\SecretRepository;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/src/bootstrap.php';

$config = Config::load();
$pdo = Database::connect($config);
$limits = $config->get('rate_limit');
$pow = $config->get('pow');

$secrets = (new SecretRepository($pdo, (int) $config->get('max_failed_attempts')))->purge();
$rateLimits = (new RateLimiter($pdo, $config->key('rate_limit_key'), (int) $limits['window_seconds'], []))->purge();
$challenges = (new ProofOfWork($config->key('pow_key'), (int) $pow['difficulty_bits'], (int) $pow['ttl_seconds']))->purge($pdo);

// Output only on request, so that cron sends no email for a normal run.
if (in_array('--verbose', $argv, true)) {
    echo "Purged: {$secrets} secrets, {$rateLimits} rate-limit rows, {$challenges} challenges\n";
}
