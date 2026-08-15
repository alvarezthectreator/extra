<?php
declare(strict_types=1);

function extra_store_db_config(): array
{
    return [
        'host' => getenv('EXTRA_STORE_DB_HOST') ?: '127.0.0.1',
        'port' => getenv('EXTRA_STORE_DB_PORT') ?: '8889',
        'name' => getenv('EXTRA_STORE_DB_NAME') ?: 'extra',
        'user' => getenv('EXTRA_STORE_DB_USER') ?: 'root',
        'pass' => getenv('EXTRA_STORE_DB_PASS') ?: 'root',
        'charset' => getenv('EXTRA_STORE_DB_CHARSET') ?: 'utf8mb4',
    ];
}

function extra_store_pdo(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $config = extra_store_db_config();
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        $config['host'],
        $config['port'],
        $config['name'],
        $config['charset']
    );

    $pdo = new PDO($dsn, $config['user'], $config['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}
