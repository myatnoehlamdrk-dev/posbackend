<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
         web: __DIR__.'/../routes/web.php',
         api: __DIR__.'/../routes/api.php',
         apiPrefix: 'api',
         commands: __DIR__.'/../routes/console.php',
         health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // The framework's default guest redirect is `fn () => route('login')`,
        // and this app has never had a login route — it is a Flutter client,
        // not a browser app. That closure is called *while constructing* the
        // AuthenticationException, so the RouteNotFoundException it threw
        // escaped first: every unauthenticated API request came back as a 500
        // with "Route [login] not defined", and the JSON rendering configured
        // below never got the chance to answer 401. Redirecting guests to the
        // one web route that exists keeps that construction safe; API requests
        // do not use it at all, since they are answered with 401 JSON.
        $middleware->redirectGuestsTo('/');

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
        // Idempotency runs before throttling so an outbox replay of an
        // already-completed request is answered from cache without
        // spending rate-limit budget (and cannot be starved by it).
        $middleware->api(append: [
            \App\Http\Middleware\IdempotencyMiddleware::class,
            \Illuminate\Routing\Middleware\ThrottleRequests::class.':api',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // An expired or missing token on an `api/*` request used to surface as
        // a 500 -- `Route [login] not defined` -- rather than the 401 it is,
        // and behind a non-JSON request as an HTML error page. The client is a
        // Flutter app: it cannot follow a redirect to a form it has no way to
        // submit, so it needs the JSON 401 it already knows how to parse. The
        // companion half of this is `redirectGuestsTo('/')` above, which stops
        // the redirect target itself from throwing before the exception is
        // even constructed.
        $exceptions->shouldRenderJsonWhen(
            static fn (Request $request): bool => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(static function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            // Not an API call: leave the framework's behaviour alone.
            return null;
        });
    })->create();
