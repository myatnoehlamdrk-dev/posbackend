<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Replays a cached response for a repeated Idempotency-Key instead of
 * executing the request again.
 *
 * The offline outbox tags every POST it retries with an Idempotency-Key
 * (one uuid per queued entry). A request can fail on the client after it
 * already succeeded on the server -- a timeout after the commit, a
 * connection dropped before the response arrived -- and without this
 * middleware the retry would execute a second time: a duplicate sale, a
 * second draft order pinning stock twice.
 *
 * Only successful (2xx) responses are cached. Failures evict the key so a
 * retry gets a fresh chance: a 401 that expires, a 429 that clears, or a
 * 500 that was transient must not replay forever from a cache entry.
 * Business rejections (4xx) never reach a replay anyway -- the outbox
 * marks them rejected rather than retrying.
 */
class IdempotencyMiddleware
{
    /**
     * How long a response stays replayable. Long enough to cover an
     * outbox draining after a multi-hour outage, short enough that a
     * stuck key cannot outlive a day.
     */
    private const TTL_SECONDS = 86400;

    /**
     * How long the "currently executing" claim is held. A process that
     * dies mid-request releases its claim after this window.
     */
    private const PENDING_TTL_SECONDS = 300;

    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('Idempotency-Key');
        if ($key === null || $key === '') {
            return $next($request);
        }

        if (! in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return $next($request);
        }

        // The key is scoped to method + path: the outbox reuses one entry
        // uuid for both halves of a compound draft-then-sale pair, and the
        // two different endpoints must not share a cached response.
        $cacheKey = 'idempotency:'.sha1($request->method().'|'.$request->path().'|'.$key);

        $replayed = $this->replayIfCompleted($cacheKey);
        if ($replayed !== null) {
            return $replayed;
        }

        // Claim the key. add() is atomic: only one caller wins, everyone
        // else sees the claim and treats the request as in flight.
        if (! Cache::add($cacheKey, 'pending', self::PENDING_TTL_SECONDS)) {
            return $this->replayIfCompleted($cacheKey)
                ?? $this->inProgress();
        }

        try {
            $response = $next($request);
        } catch (\Throwable $e) {
            Cache::forget($cacheKey);
            throw $e;
        }

        if (! $response->isSuccessful()) {
            // Failure: release the key so a retry re-executes.
            Cache::forget($cacheKey);

            return $response;
        }

        Cache::put($cacheKey, [
            'status' => $response->getStatusCode(),
            'body' => $response->getContent(),
            'content_type' => $response->headers->get('Content-Type'),
        ], self::TTL_SECONDS);

        return $response;
    }

    /**
     * Returns a replayed response when this key has already completed,
     * or null when nothing is stored yet.
     */
    private function replayIfCompleted(string $cacheKey): ?Response
    {
        $stored = Cache::get($cacheKey);
        if (! is_array($stored) || ! isset($stored['status'])) {
            return null;
        }

        $response = new Response($stored['body'] ?? '', (int) $stored['status']);
        if ($contentType = $stored['content_type'] ?? null) {
            $response->headers->set('Content-Type', $contentType);
        }
        $response->headers->set('Idempotency-Replayed', 'true');

        return $response;
    }

    /**
     * Same key is mid-flight in another process; the client should retry
     * shortly. 503 is deliberate: the outbox classifies 5xx as "try again
     * later" while 4xx would mark the entry rejected forever.
     */
    private function inProgress(): Response
    {
        $response = new Response(
            json_encode(['message' => 'A request with this Idempotency-Key is already in progress.']),
            503,
            ['Content-Type' => 'application/json', 'Retry-After' => '2']
        );

        return $response;
    }
}
