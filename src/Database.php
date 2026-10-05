<?php

declare(strict_types=1);

namespace Vaporised;

use PDO;

final class Database
{
    public static function connect(Config $config): PDO
    {
        $db = $config->get('db');
        $pdo = new PDO($db['dsn'], $db['user'], $db['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $pdo->exec("SET time_zone = '+00:00'");

        return $pdo;
    }
}
