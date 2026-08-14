<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Este script solo puede ejecutarse por consola.\n");
    exit(1);
}

require __DIR__ . DIRECTORY_SEPARATOR . 'bootstrap.php';

$host = (string) env('DB_HOST', '127.0.0.1');
$port = (string) env('DB_PORT', '3306');
$database = (string) env('DB_DATABASE', 'seguridad_municipal');
$username = (string) env('DB_USERNAME', 'root');
$password = (string) env('DB_PASSWORD', '');

if (!preg_match('/^[A-Za-z0-9_]+$/', $database)) {
    fwrite(STDERR, "Nombre de base de datos inválido.\n");
    exit(1);
}

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $root = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $username, $password, $options);
    $root->exec("CREATE DATABASE IF NOT EXISTS `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $root->exec("USE `{$database}`");

    $root->exec(
        'CREATE TABLE IF NOT EXISTS migrations (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            filename VARCHAR(255) NOT NULL UNIQUE,
            ran_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $doneStmt = $root->query('SELECT filename FROM migrations');
    $done = $doneStmt->fetchAll(PDO::FETCH_COLUMN);

    $files = glob(__DIR__ . DIRECTORY_SEPARATOR . 'migrations' . DIRECTORY_SEPARATOR . '*.sql') ?: [];
    sort($files);

    foreach ($files as $file) {
        $name = basename($file);

        if (in_array($name, $done, true)) {
            echo "Omitida: {$name}\n";
            continue;
        }

        $sql = file_get_contents($file);

        if ($sql === false || trim($sql) === '') {
            continue;
        }

        $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;
        $statements = array_filter(array_map('trim', explode(';', $sql)));

        foreach ($statements as $statement) {
            $root->exec($statement);
        }
        $insert = $root->prepare('INSERT INTO migrations (filename) VALUES (:filename)');
        $insert->execute(['filename' => $name]);
        echo "Ejecutada: {$name}\n";
    }

    echo "Migraciones completadas.\n";
} catch (PDOException $e) {
    fwrite(STDERR, 'Error de base de datos: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
