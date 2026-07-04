<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            \Illuminate\Support\Facades\Route::middleware('api')->group(function () {
                \Illuminate\Support\Facades\Route::get('/webhook/whatsapp', function (\Illuminate\Http\Request $request) {
                    if ($request->query('hub_mode') === 'subscribe' &&
                        $request->query('hub_verify_token') === env('META_VERIFY_TOKEN')) {
                        return response($request->query('hub_challenge'), 200);
                    }
                    return response('Forbidden', 403);
                });
                \Illuminate\Support\Facades\Route::post('/webhook/whatsapp', [\App\Http\Controllers\WhatsAppController::class, 'handle']);
            });
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();