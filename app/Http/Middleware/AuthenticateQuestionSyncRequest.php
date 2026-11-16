<?php

namespace App\Http\Middleware;

use App\Services\QuestionSyncSource;
use App\Support\QuestionSync;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lets a request for the live server's questions through only on the
 * server that shares them (anywhere else the API isn't there at all), only
 * while its Super Admin has the sharing open, and only when it is signed
 * with the shared secret (QuestionSync). Every request let through is
 * logged.
 */
class AuthenticateQuestionSyncRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! QuestionSync::isSource()) {
            abort(Response::HTTP_NOT_FOUND);
        }

        if (! QuestionSync::verify($request)) {
            return response()->json(['message' => 'The request is not signed with the right secret.'], Response::HTTP_UNAUTHORIZED);
        }

        if (! QuestionSyncSource::isOpen()) {
            return response()->json(['message' => 'Question sharing is closed on the live server.'], Response::HTTP_FORBIDDEN);
        }

        Log::info('Question sharing request.', ['path' => $request->path(), 'query' => $request->query(), 'ip' => $request->ip()]);

        return $next($request);
    }
}
