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

function extra_store_admin_email(): string
{
    return 'wagwulageorge@gmail.com';
}

function extra_store_mailer_config(): array
{
    $transport = strtolower(trim((string) (getenv('EXTRA_STORE_MAIL_TRANSPORT') ?: 'smtp')));
    $host = trim((string) (getenv('EXTRA_STORE_SMTP_HOST') ?: 'mail.mocktailcanapes.com'));
    $username = trim((string) (getenv('EXTRA_STORE_SMTP_USERNAME') ?: 'test@mocktailcanapes.com'));
    $password = (string) (getenv('EXTRA_STORE_SMTP_PASSWORD') ?: 'Alvarez.1000');
    $fromEmail = trim((string) (getenv('EXTRA_STORE_FROM_EMAIL') ?: 'test@mocktailcanapes.com'));
    $fromName = trim((string) (getenv('EXTRA_STORE_FROM_NAME') ?: 'Extra Store'));
    $encryption = strtolower(trim((string) (getenv('EXTRA_STORE_SMTP_ENCRYPTION') ?: 'ssl')));
    $port = (int) (getenv('EXTRA_STORE_SMTP_PORT') ?: 465);

    return [
        'transport' => in_array($transport, ['mail', 'smtp'], true) ? $transport : 'mail',
        'host' => $host,
        'username' => $username,
        'password' => $password,
        'from_email' => filter_var($fromEmail, FILTER_VALIDATE_EMAIL) ? $fromEmail : 'wagwulageorge@gmail.com',
        'from_name' => $fromName !== '' ? $fromName : 'Extra Store',
        'encryption' => in_array($encryption, ['tls', 'ssl', ''], true) ? $encryption : 'tls',
        'port' => $port > 0 ? $port : 587,
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
