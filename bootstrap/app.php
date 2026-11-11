<?php

use App\Http\Controllers\TileExplorerController;
use App\Http\Middleware\EnsureUserIsEndUser;
use App\Http\Middleware\EnsureUserIsExpert;
use App\Http\Middleware\IsAdmin;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // cloudflared runs on this machine, so only trust forwarded headers from localhost.
        $middleware->trustProxies(at: ['127.0.0.1', '::1']);

        // Tile Explorer: the page reads this cookie to see that a ZIP download has started.
        $middleware->encryptCookies(except: [TileExplorerController::DOWNLOAD_COOKIE]);

        $middleware->alias([
            'admin' => IsAdmin::class,
            'end_user' => EnsureUserIsEndUser::class,
            'expert' => EnsureUserIsExpert::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
