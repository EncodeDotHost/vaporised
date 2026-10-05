<?php

declare(strict_types=1);

namespace Vaporised;

use PDO;

final class Controller
{
    private const TOKEN_PATTERN = '[A-Za-z0-9_-]{43}';
    private const MAX_PASSPHRASE_CHARS = 200;
    private const DEFAULT_DESCRIPTION = 'Send a password or other secret by a one-time link. '
        . 'Encrypted, deleted after the last view or the time limit, and no cookies.';

    private ?PDO $pdo = null;

    public function __construct(private Config $config, private string $templateDir)
    {
    }

    public function dispatch(string $method, string $path): void
    {
        if ($method === 'HEAD') {
            $method = 'GET';
        }
        $isGet = $method === 'GET';
        $isPost = $method === 'POST';

        if ($path === '/' && $isGet) {
            $this->showCreate();
        } elseif ($path === '/help' && $isGet) {
            $this->showHelp();
        } elseif ($path === '/privacy' && $isGet) {
            $this->render('privacy', [
                'title' => 'Privacy policy',
                'description' => 'What data Vaporised keeps, why, and for how long. No cookies, no analytics, no third parties.',
                'canonicalPath' => '/privacy',
            ]);
        } elseif ($path === '/' && $isPost) {
            $this->create();
        } elseif ($path === '/challenge' && $isGet) {
            $this->challenge();
        } elseif (preg_match('#^/s/(' . self::TOKEN_PATTERN . ')$#', $path, $m) && $isGet) {
            $this->showReveal($m[1]);
        } elseif (preg_match('#^/s/(' . self::TOKEN_PATTERN . ')$#', $path, $m) && $isPost) {
            $this->reveal($m[1]);
        } elseif (preg_match('#^/d/(' . self::TOKEN_PATTERN . ')$#', $path, $m) && $isGet) {
            $this->showDelete($m[1]);
        } elseif (preg_match('#^/d/(' . self::TOKEN_PATTERN . ')$#', $path, $m) && $isPost) {
            $this->delete($m[1]);
        } else {
            $this->message('Page not found', 'This page does not exist.', 404);
        }
    }

    private function showCreate(?string $error = null, array $old = [], int $status = 200): void
    {
        $this->render('create', [
            'title' => 'Share a secret',
            'error' => $error,
            'old' => $old + ['secret' => '', 'ttl' => 86400, 'views' => 1],
            'ttlChoices' => $this->config->ttlChoices(),
            'maxViews' => (int) $this->config->get('max_views'),
            'maxSecretChars' => (int) $this->config->get('max_secret_chars'),
            'maxPassphraseChars' => self::MAX_PASSPHRASE_CHARS,
            'canonicalPath' => $error === null ? '/' : null,
        ], $status);
    }

    private function showHelp(): void
    {
        $this->render('help', [
            'title' => 'Help',
            'description' => 'How to send, read, and delete a secret with Vaporised.',
            'canonicalPath' => '/help',
            'ttlChoices' => $this->config->ttlChoices(),
            'maxViews' => (int) $this->config->get('max_views'),
            'maxSecretChars' => (int) $this->config->get('max_secret_chars'),
            'maxFailedAttempts' => (int) $this->config->get('max_failed_attempts'),
        ]);
    }

    private function create(): void
    {
        if (!$this->limiter()->hit('create', $this->clientIp())) {
            $this->tooManyRequests();
            return;
        }

        $secret = $this->postString('secret');
        $passphrase = $this->postString('passphrase');
        $ttl = (int) $this->postString('ttl');
        $views = (int) $this->postString('views');
        $old = ['secret' => $secret, 'ttl' => $ttl, 'views' => $views];

        $secret = str_replace("\r\n", "\n", $secret);
        $maxChars = (int) $this->config->get('max_secret_chars');
        $maxViews = (int) $this->config->get('max_views');

        $error = match (true) {
            $secret === '' => 'Enter a secret.',
            !mb_check_encoding($secret, 'UTF-8') => 'The secret is not valid text.',
            mb_strlen($secret, 'UTF-8') > $maxChars => 'The secret is too long. The maximum is ' . $maxChars . ' characters.',
            mb_strlen($passphrase, 'UTF-8') > self::MAX_PASSPHRASE_CHARS => 'The passphrase is too long.',
            !array_key_exists($ttl, $this->config->ttlChoices()) => 'Select a time limit from the list.',
            $views < 1 || $views > $maxViews => 'The view count must be from 1 to ' . $maxViews . '.',
            default => null,
        };
        if ($error !== null) {
            $this->showCreate($error, $old, 422);
            return;
        }

        if (!$this->pow()->verify($this->pdo(), $this->postString('pow_challenge'), $this->postString('pow_nonce'))) {
            $this->showCreate('The browser check failed. Try again. JavaScript is necessary to create a secret.', $old, 422);
            return;
        }

        $tokens = $this->secrets()->create($secret, $passphrase, $ttl, $views);
        $baseUrl = rtrim((string) $this->config->get('base_url'), '/');

        $this->render('created', [
            'title' => 'Secret saved',
            'shareUrl' => $baseUrl . '/s/' . $tokens['share_token'],
            'deleteUrl' => $baseUrl . '/d/' . $tokens['delete_token'],
            'ttlLabel' => $this->config->ttlChoices()[$ttl],
            'views' => $views,
            'hasPassphrase' => $passphrase !== '',
        ]);
    }

    private function challenge(): void
    {
        header('Content-Type: application/json');
        echo json_encode($this->pow()->issue());
    }

    private function showReveal(string $token, ?string $error = null, int $status = 200): void
    {
        $found = $this->secrets()->find($token);
        if ($found === null) {
            $this->gone();
            return;
        }
        $this->render('confirm', [
            'title' => 'A secret is ready for you',
            'error' => $error,
            'hasPassphrase' => $found['has_passphrase'],
            'maxPassphraseChars' => self::MAX_PASSPHRASE_CHARS,
        ], $status);
    }

    private function reveal(string $token): void
    {
        if (!$this->limiter()->hit('reveal', $this->clientIp())) {
            $this->tooManyRequests();
            return;
        }

        $result = $this->secrets()->reveal($token, $this->postString('passphrase'));
        if ($result['status'] === 'bad_passphrase') {
            $this->showReveal($token, 'The passphrase is incorrect.', 403);
            return;
        }
        if ($result['status'] !== 'ok') {
            $this->gone();
            return;
        }
        $this->render('reveal', [
            'title' => 'Your secret',
            'secret' => $result['secret'],
            'viewsRemaining' => $result['views_remaining'],
        ]);
    }

    private function showDelete(string $token): void
    {
        if (!$this->secrets()->existsByDeleteToken($token)) {
            $this->gone();
            return;
        }
        $this->render('delete', ['title' => 'Delete this secret']);
    }

    private function delete(string $token): void
    {
        if (!$this->secrets()->deleteByDeleteToken($token)) {
            $this->gone();
            return;
        }
        $this->message('Secret deleted', 'The secret is deleted. The share link does not work now.');
    }

    private function gone(): void
    {
        $this->message(
            'Secret not available',
            'This secret does not exist, has expired, or has no views left.',
            404
        );
    }

    private function tooManyRequests(): void
    {
        $this->message('Too many requests', 'You made too many requests. Try again later.', 429);
    }

    private function message(string $title, string $text, int $status = 200): void
    {
        $this->render('message', ['title' => $title, 'text' => $text], $status);
    }

    private function render(string $template, array $vars, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: text/html; charset=utf-8');
        // Only public information pages have a canonical path and are indexable.
        // Other pages point og:url at the home page, so that a link preview never repeats a token.
        $canonicalPath = $vars['canonicalPath'] ?? null;
        $baseUrl = rtrim((string) $this->config->get('base_url'), '/');
        $vars['robots'] = $canonicalPath !== null ? 'index, follow' : 'noindex, nofollow';
        $vars['baseUrl'] = $baseUrl;
        $vars['pageUrl'] = $baseUrl . ($canonicalPath ?? '/');
        $vars['description'] ??= self::DEFAULT_DESCRIPTION;
        header('X-Robots-Tag: ' . $vars['robots']);
        extract($vars, EXTR_SKIP);
        ob_start();
        require $this->templateDir . '/' . $template . '.php';
        $content = ob_get_clean();
        require $this->templateDir . '/layout.php';
    }

    private function postString(string $name): string
    {
        $value = $_POST[$name] ?? '';

        return is_string($value) ? $value : '';
    }

    private function clientIp(): string
    {
        return (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    }

    private function pdo(): PDO
    {
        return $this->pdo ??= Database::connect($this->config);
    }

    private function secrets(): SecretRepository
    {
        return new SecretRepository(
            $this->pdo(),
            new Crypto($this->config->key('master_key')),
            (int) $this->config->get('max_failed_attempts')
        );
    }

    private function limiter(): RateLimiter
    {
        $limits = $this->config->get('rate_limit');

        return new RateLimiter(
            $this->pdo(),
            $this->config->key('rate_limit_key'),
            (int) $limits['window_seconds'],
            ['create' => (int) $limits['create'], 'reveal' => (int) $limits['reveal']]
        );
    }

    private function pow(): ProofOfWork
    {
        $pow = $this->config->get('pow');

        return new ProofOfWork(
            $this->config->key('pow_key'),
            (int) $pow['difficulty_bits'],
            (int) $pow['ttl_seconds']
        );
    }
}
