<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Services\JwtService;
use App\Exceptions\JwtException;

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
                throw JwtException::tokenNotProvided();
            }

            $user = $this->jwtService->getUserFromToken($token);

            if (!$user) {
                throw JwtException::tokenInvalid();
            }

            if (!$user->is_active) {
                throw JwtException::tokenInvalid();
            }

            // set authenticated user
            auth()->setUser($user);
            $request->setUserResolver(function () use ($user) {
                return $user;
            });

        } catch (JwtException $e) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => $e->getMessage()
            ], $e->getCode());
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Invalid token'
            ], 401);
        }

        return $next($request);
    }
}
