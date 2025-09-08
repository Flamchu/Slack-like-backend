<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Services\JwtService;
use App\Exceptions\JwtException;
use Illuminate\Support\Facades\Log;

class JwtAuthMiddleware
{
    protected JwtService $jwtService;

    public function __construct(JwtService $jwtService)
    {
        $this->jwtService = $jwtService;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $token = $this->jwtService->getTokenFromRequest($request);

            if (!$token) {
                throw JwtException::tokenNotProvided([
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'path' => $request->path()
                ]);
            }

            $user = $this->jwtService->getUserFromToken($token);

            if (!$user) {
                throw JwtException::userNotFound([
                    'ip' => $request->ip(),
                    'token_hash' => hash('sha256', $token)
                ]);
            }

            // set authenticated user
            auth()->setUser($user);
            $request->setUserResolver(function () use ($user) {
                return $user;
            });

            $request->attributes->set('authenticated_user', $user);

            // log successful authentication
            Log::debug('JWT authentication successful', [
                'user_id' => $user->id,
                'ip' => $request->ip(),
                'path' => $request->path(),
                'method' => $request->method()
            ]);

        } catch (JwtException $e) {
            // log authentication failure
            Log::warning('JWT authentication failed', [
                'error_code' => $e->getErrorCode(),
                'message' => $e->getMessage(),
                'ip' => $request->ip(),
                'path' => $request->path(),
                'context' => $e->getContext()
            ]);

            return $e->render($request);
        } catch (\Exception $e) {
            // log unexpected errors
            Log::error('Unexpected JWT middleware error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'ip' => $request->ip(),
                'path' => $request->path()
            ]);

            return response()->json([
                'error' => 'Authentication Error',
                'message' => 'An unexpected error occurred during authentication',
                'code' => 'UNEXPECTED_ERROR',
                'timestamp' => now()->toISOString()
            ], 401);
        }

        return $next($request);
    }
}
