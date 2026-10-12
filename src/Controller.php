<?php

declare(strict_types=1);

namespace Vaporised;

use PDO;

final class Controller
{
    private const TOKEN_PATTERN = '[A-Za-z0-9_-]{43}';
    private const MAX_PASSPHRASE_CHARS = 200;
    /** Version byte, 16-byte salt, 12-byte iv, and 16-byte tag. Must match public/assets/crypto.js. */
    private const CIPHERTEXT_OVERHEAD = 45;
    private const GONE_TEXT = 'This secret does not exist, has expired, or has no views left.';
    private const DEFAULT_DESCRIPTION = 'Send a password or other secret by a link that deletes itself '
        . 'after it is read. Encrypted, no sign-up, and no cookies.';

    private ?PDO $pdo = null;

    public function __construct(private Config $config, private string $templateDir)
    {
    }

    public function dispatch(string $method, string $path): void
    {
        if ($this->redirectToBaseUrl()) {
            return;
        }
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
                'description' => 'Vaporised sets no cookies, uses no analytics, and shares no data. '
                    . 'See what we keep, why, and when we delete it.',
                'canonicalPath' => '/privacy',
            ]);
        } elseif ($path === '/secrets' && $isPost) {
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

    private function showCreate(): void
    {
        $this->render('create', [
            'title' => 'Share a secret',
            'pageTitle' => 'Vaporised – Send passwords by self-destructing link',
            'ttlChoices' => $this->config->ttlChoices(),
            'maxViews' => (int) $this->config->get('max_views'),
            'maxSecretChars' => (int) $this->config->get('max_secret_chars'),
            'maxPassphraseChars' => self::MAX_PASSPHRASE_CHARS,
            'canonicalPath' => '/',
        ]);
    }

    private function showHelp(): void
    {
        $this->render('help', [
            'title' => 'Help',
            'description' => 'Step-by-step help to send, read, and delete a self-destructing secret link, '
                . 'with answers to common questions.',
            'canonicalPath' => '/help',
            'ttlChoices' => $this->config->ttlChoices(),
            'maxViews' => (int) $this->config->get('max_views'),
            'maxSecretChars' => (int) $this->config->get('max_secret_chars'),
            'maxFailedAttempts' => (int) $this->config->get('max_failed_attempts'),
        ]);
    }

    /**
     * JSON endpoint. The browser sends ciphertext that it made with a key the
     * server never sees. The server checks only the size and the form values.
     */
    private function create(): void
    {
        if (!$this->limiter()->hit('create', $this->clientIp())) {
            $this->json(['error' => 'You made too many requests. Try again later.'], 429);
            return;
        }

        $ciphertext = Crypto::b64uDecode($this->postString('ciphertext'));
        $passphrase = $this->postString('passphrase');
        $ttl = (int) $this->postString('ttl');
        $views = (int) $this->postString('views');

        $maxChars = (int) $this->config->get('max_secret_chars');
        $maxViews = (int) $this->config->get('max_views');
        // Version, salt, iv, and tag, plus up to 4 bytes for each UTF-8 character.
        $maxBytes = self::CIPHERTEXT_OVERHEAD + $maxChars * 4;

        $error = match (true) {
            $ciphertext === null || strlen($ciphertext) < self::CIPHERTEXT_OVERHEAD => 'The encrypted secret is not valid.',
            strlen($ciphertext) > $maxBytes => 'The secret is too long. The maximum is ' . $maxChars . ' characters.',
            !mb_check_encoding($passphrase, 'UTF-8') => 'The passphrase is not valid text.',
            mb_strlen($passphrase, 'UTF-8') > self::MAX_PASSPHRASE_CHARS => 'The passphrase is too long.',
            !array_key_exists($ttl, $this->config->ttlChoices()) => 'Select a time limit from the list.',
            $views < 1 || $views > $maxViews => 'The view count must be from 1 to ' . $maxViews . '.',
            default => null,
        };
        if ($error !== null) {
            $this->json(['error' => $error], 422);
            return;
        }

        if (!$this->pow()->verify($this->pdo(), $this->postString('pow_challenge'), $this->postString('pow_nonce'))) {
            $this->json(['error' => 'The browser check failed. Try again.'], 422);
            return;
        }

        $tokens = $this->secrets()->create($ciphertext, $passphrase, $ttl, $views);
        $baseUrl = rtrim((string) $this->config->get('base_url'), '/');

        // The browser adds "#" and the key to the share link.
        $this->json([
            'share_url' => $baseUrl . '/s/' . $tokens['share_token'],
            'delete_url' => $baseUrl . '/d/' . $tokens['delete_token'],
        ]);
    }

    private function challenge(): void
    {
        header('Content-Type: application/json');
        echo json_encode($this->pow()->issue());
    }

    private function showReveal(string $token): void
    {
        $found = $this->secrets()->find($token);
        if ($found === null) {
            $this->gone();
            return;
        }
        $this->render('confirm', [
            'title' => 'A secret is ready for you',
            'hasPassphrase' => $found['has_passphrase'],
            'maxPassphraseChars' => self::MAX_PASSPHRASE_CHARS,
        ]);
    }

    /** JSON endpoint. Gives out the ciphertext; the browser decrypts it with the key after "#". */
    private function reveal(string $token): void
    {
        if (!$this->limiter()->hit('reveal', $this->clientIp())) {
            $this->json(['error' => 'You made too many requests. Try again later.'], 429);
            return;
        }

        $result = $this->secrets()->reveal($token, $this->postString('passphrase'));
        if ($result['status'] === 'bad_passphrase') {
            $this->json(['error' => 'The passphrase is incorrect.'], 403);
            return;
        }
        if ($result['status'] !== 'ok') {
            $this->json(['error' => self::GONE_TEXT, 'gone' => true], 404);
            return;
        }
        $this->json([
            'ciphertext' => Crypto::b64u($result['ciphertext']),
            'views_remaining' => $result['views_remaining'],
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

    /**
     * Send a request for any other host name to the host in base_url, so that
     * the site has one address. The browser keeps the "#" part of a link.
     */
    private function redirectToBaseUrl(): bool
    {
        $base = parse_url((string) $this->config->get('base_url'));
        $expected = strtolower($base['host'] ?? '') . (isset($base['port']) ? ':' . $base['port'] : '');
        $actual = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
        if ($expected === '' || $actual === $expected) {
            return false;
        }
        $target = rtrim((string) $this->config->get('base_url'), '/') . ($_SERVER['REQUEST_URI'] ?? '/');
        http_response_code(301);
        header('Location: ' . $target);

        return true;
    }

    private function gone(): void
    {
        $this->message('Secret not available', self::GONE_TEXT, 404);
    }

    private function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data);
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
        $vars['canonicalUrl'] = $canonicalPath !== null ? $baseUrl . $canonicalPath : null;
        $vars['description'] ??= self::DEFAULT_DESCRIPTION;
        $vars['pageTitle'] ??= $vars['title'] . ' – Vaporised';
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
        return new SecretRepository($this->pdo(), (int) $this->config->get('max_failed_attempts'));
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
