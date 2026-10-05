<?php

declare(strict_types=1);

namespace Vaporised;

use RuntimeException;

final class Config
{
    /** Hard limit. The config file cannot increase it. */
    public const MAX_TTL_SECONDS = 604800;

    private function __construct(private array $values)
    {
    }

    /**
     * Load the config file. The default location is one level above the
     * document root (the repository root), for example
     * /home/vaporised/vaporised-config.php. The VAPORISED_CONFIG environment
     * variable overrides the location for local development and tests.
     */
    public static function load(?string $path = null): self
    {
        $path ??= getenv('VAPORISED_CONFIG') ?: dirname(__DIR__, 2) . '/vaporised-config.php';
        if (!is_file($path)) {
            throw new RuntimeException('Config file not found: ' . $path);
        }
        $values = require $path;
        if (!is_array($values)) {
            throw new RuntimeException('Config file must return an array.');
        }

        return new self($values);
    }

    public function get(string $key): mixed
    {
        if (!array_key_exists($key, $this->values)) {
            throw new RuntimeException('Config value is missing: ' . $key);
        }

        return $this->values[$key];
    }

    /** Return a 32-byte key that the config file holds as base64. */
    public function key(string $name): string
    {
        $key = base64_decode((string) $this->get($name), true);
        if ($key === false || strlen($key) !== 32) {
            throw new RuntimeException('Config key must be 32 bytes in base64: ' . $name);
        }

        return $key;
    }

    /** @return array<int, string> Time limit choices (seconds => label), never more than 7 days. */
    public function ttlChoices(): array
    {
        return array_filter(
            $this->get('ttl_choices'),
            static fn (int $seconds): bool => $seconds > 0 && $seconds <= self::MAX_TTL_SECONDS,
            ARRAY_FILTER_USE_KEY
        );
    }
}
