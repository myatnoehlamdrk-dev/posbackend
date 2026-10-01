<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
         web: __DIR__.'/../routes/web.php',
         api: __DIR__.'/../routes/api.php',
         apiPrefix: 'api',
         commands: __DIR__.'/../routes/console.php',
         health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(\Illuminate\Http\Middleware\HandleCors::class);
        $middleware->alias([
            'admin' => \App\Http\Middleware\AdminMiddleware::class,
        ]);

        // Rate limiting, applied per route group rather than globally.
        //
        // A POS terminal is not a browser: it sits on shop wifi, several
        // tills can share one NAT address, and the screens load data in bursts
        // on navigation. A single global low limit would throttle a busy shop
        // during trading for no security benefit. The limits below are shaped
        // around what each group actually is -- auth is credential stuffing,
        // writes are inventory entry, reads are idle screen refreshes.
        //
        // `throttle:<name>` resolves to a limiter defined in AppServiceProvider.
        // Laravel emits a standard `Retry-After` header on 429, which is exactly
        // what the Flutter client parses to decide when to try again, so no
        // custom header or JSON body shape is invented here.
        $middleware->api(append: [
            \Illuminate\Routing\Middleware\ThrottleRequests::class.':api',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
