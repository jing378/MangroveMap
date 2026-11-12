<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Apache on Windows runs PHP in threads that share one process environment, and putenv()/getenv() are not
// thread-safe. Under parallel requests (e.g. the Tile Explorer's thumbnails) a request could miss the .env
// values and fail as "production" with "No application encryption key". Keep .env values per request
// ($_ENV/$_SERVER only). Web requests only: artisan and queue workers are not threaded.
Illuminate\Support\Env::disablePutenv();

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
