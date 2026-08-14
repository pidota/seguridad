<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

if (version_compare(PHP_VERSION, '8.3.0', '<')) {
    http_response_code(500);
    echo 'Este sistema requiere PHP 8.3 o superior.';
    exit;
}

if (PHP_SAPI === 'cli-server') {
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $file = __DIR__ . $path;

    if ($path !== '/' && is_file($file)) {
        return false;
    }
}

require BASE_PATH . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'Autoloader.php';

Core\Autoloader::register();

require BASE_PATH . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Helpers' . DIRECTORY_SEPARATOR . 'functions.php';

$app = new Core\App();
$app->run();
