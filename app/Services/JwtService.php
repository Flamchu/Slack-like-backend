<?php

namespace App\Services;

use App\Models\User;
use App\Exceptions\JwtException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class JwtService
{
    private string $secret;
    private string $algorithm;
    private int $ttl;
    private int $refreshTtl;

    public function __construct()
    {
        $this->secret = config('jwt.secret');
        $this->algorithm = config('jwt.algo');
        $this->ttl = config('jwt.ttl');
        $this->refreshTtl = config('jwt.refresh_ttl');
    }

    /**
     * Generate JWT token for user
     */
    public function generateToken(User $user): string
    {
        $now = Carbon::now();
        $payload = [
            'iss' => config('app.url'), // issuer
            'sub' => $user->id, // user id
            'iat' => $now->timestamp, // issued at
            'exp' => $now->copy()->addMinutes($this->ttl)->timestamp, // expires
            'nbf' => $now->timestamp, // not before
            'jti' => uniqid(), // jwt id
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'role' => $user->role,
            ]
        ];

        return JWT::encode($payload, $this->secret, $this->algorithm);
    }

    /**
     * Validate JWT token
     */
    public function validateToken(string $token): bool
    {
        try {
            $this->decodeToken($token);
            return !$this->isTokenBlacklisted($token);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get user from token
     */
    public function getUserFromToken(string $token): ?User
    {
        try {
            $payload = $this->decodeToken($token);

            if ($this->isTokenBlacklisted($token)) {
                throw JwtException::tokenBlacklisted();
            }

            return User::find($payload->sub);
        } catch (ExpiredException $e) {
            throw JwtException::tokenExpired();
        } catch (SignatureInvalidException $e) {
            throw JwtException::tokenInvalid();
        } catch (\Exception $e) {
            throw JwtException::tokenInvalid();
        }
    }

    /**
     * Refresh JWT token
     */
    public function refreshToken(string $token): string
    {
        try {
            $payload = $this->decodeToken($token, false); // skip expiration check

            if ($this->isTokenBlacklisted($token)) {
                throw JwtException::tokenBlacklisted();
            }

            $user = User::find($payload->sub);
            if (!$user) {
                throw JwtException::tokenInvalid();
            }

            // blacklist old token
            $this->blacklistToken($token);

            // create new token
            return $this->generateToken($user);
        } catch (\Exception $e) {
            throw JwtException::tokenInvalid();
        }
    }

    /**
     * Invalidate token (blacklist)
     */
    public function invalidateToken(string $token): bool
    {
        try {
            $this->blacklistToken($token);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Decode JWT token
     */
    private function decodeToken(string $token, bool $validateExpiration = true): object
    {
        if (!$validateExpiration) {
            // skip expiration check for refresh tokens
            // catch expired exception and check refresh window
            try {
                return JWT::decode($token, new Key($this->secret, $this->algorithm));
            } catch (ExpiredException $e) {
                // get payload to check refresh window
                $parts = explode('.', $token);
                if (count($parts) !== 3) {
                    throw new \Exception('Invalid token format');
                }

                $payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $parts[1])));
                if (!$payload || !isset($payload->iat)) {
                    throw new \Exception('Invalid token payload');
                }

                // check if token too old for refresh
                if (time() - $payload->iat > $this->refreshTtl * 60) {
                    throw new ExpiredException('Token is too old to refresh');
                }

                // expired but within refresh window
                return $payload;
            }
        }

        return JWT::decode($token, new Key($this->secret, $this->algorithm));
    }

    /**
     * Blacklist a token
     */
    private function blacklistToken(string $token): void
    {
        $tokenHash = hash('sha256', $token);
        Cache::put("blacklisted_token:{$tokenHash}", true, $this->refreshTtl * 60);
    }

    /**
     * Check if token is blacklisted
     */
    private function isTokenBlacklisted(string $token): bool
    {
        $tokenHash = hash('sha256', $token);
        return Cache::has("blacklisted_token:{$tokenHash}");
    }

    /**
     * Extract token from request header
     */
    public function getTokenFromRequest($request = null): ?string
    {
        if ($request === null) {
            $request = request();
        }

        $header = $request->header('Authorization');

        if (!$header || !str_starts_with($header, 'Bearer ')) {
            return null;
        }

        return substr($header, 7);
    }
}
