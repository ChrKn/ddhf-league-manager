<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The door to /api: a bearer token in the Authorization header, checked against the digests in
 * api_keys.
 *
 * It used to be a header of our own making, X-API-Key. Bearer says the same thing in the way every
 * client already knows how to say it, which is what lets somebody point a generic tool at this API
 * without being told about a custom header first - and it is why a refusal here carries
 * WWW-Authenticate, so the answer names the scheme it wanted instead of only complaining.
 */
class AuthenticateApiKey
{
    /**
     * Handle an incoming request.
     *
     * @param Closure(Request): (Response) $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (!$token) {
            return $this->refuse('API key missing');
        }

        $apiKey = ApiKey::forToken($token);

        if (!$apiKey) {
            return $this->refuse('Invalid or inactive API key');
        }

        // Using a key is not a change to it. Left to the timestamps, updated_at would become a
        // copy of last_used_at and stop answering the question it is there for - when did somebody
        // last touch this key's name, scope or switch.
        $apiKey->timestamps = false;
        $apiKey->update(['last_used_at' => now()]);

        return $next($request);
    }

    private function refuse(string $message): Response
    {
        return response()
            ->json(['error' => $message], 401)
            ->header('WWW-Authenticate', 'Bearer realm="DDHF Ranglisten"');
    }
}
