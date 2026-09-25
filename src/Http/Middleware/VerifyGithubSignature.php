<?php

namespace Condoedge\Projects\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Verifies the GitHub webhook HMAC (header X-Hub-Signature-256: "sha256=<hex>").
 * Modeled on app/Http/Middleware/VerifyCoolectoSignature.
 */
class VerifyGithubSignature
{
    public function handle(Request $request, Closure $next)
    {
        $secret = config('projects.github.webhook_secret');
        if (!$secret) {
            return response()->json(['error' => 'Webhook secret not configured'], 500);
        }

        $signature = (string) $request->header('X-Hub-Signature-256');
        $expected = 'sha256=' . hash_hmac('sha256', $request->getContent(), $secret);

        if (!hash_equals($expected, $signature)) {
            return response()->json(['error' => 'Invalid signature'], 403);
        }

        return $next($request);
    }
}
