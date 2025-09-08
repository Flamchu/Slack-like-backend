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
                throw JwtException::tokenBlacklisted(['token_hash' => hash('sha256', $token)]);
            }

            $user = User::find($payload->sub);

            if (!$user) {
                throw JwtException::userNotFound(['user_id' => $payload->sub]);
            }

            if (!$user->is_active) {
                throw JwtException::userInactive(['user_id' => $user->id]);
            }

            return $user;
        } catch (ExpiredException $e) {
            throw JwtException::tokenExpired(['expired_at' => $e->getMessage()]);
        } catch (SignatureInvalidException $e) {
            throw JwtException::tokenInvalid(['reason' => 'signature_invalid']);
        } catch (JwtException $e) {
            throw $e; // Re-throw JWT exceptions as-is
        } catch (\Exception $e) {
            throw JwtException::tokenMalformed(['error' => $e->getMessage()]);
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
                throw JwtException::tokenBlacklisted(['token_hash' => hash('sha256', $token)]);
            }

            $user = User::find($payload->sub);
            if (!$user) {
                throw JwtException::userNotFound(['user_id' => $payload->sub]);
            }

            if (!$user->is_active) {
                throw JwtException::userInactive(['user_id' => $user->id]);
            }

            // Validate token age for refresh window
            $tokenAge = time() - $payload->iat;
            if ($tokenAge > $this->refreshTtl * 60) {
                throw JwtException::tokenRefreshFailed(['reason' => 'token_too_old', 'age_seconds' => $tokenAge]);
            }

            // Blacklist old token
            $this->blacklistToken($token);

            // Create new token
            $newToken = $this->generateToken($user);

            // Log refresh activity
            \Log::info('JWT token refreshed', [
                'user_id' => $user->id,
                'old_token_hash' => hash('sha256', $token),
                'new_token_hash' => hash('sha256', $newToken)
            ]);

            return $newToken;
        } catch (JwtException $e) {
            throw $e; // Re-throw JWT exceptions as-is
        } catch (ExpiredException $e) {
            // Check if token is within refresh window
            $parts = explode('.', $token);
            if (count($parts) === 3) {
                $payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $parts[1])));
                if ($payload && isset($payload->iat)) {
                    $tokenAge = time() - $payload->iat;
                    if ($tokenAge <= $this->refreshTtl * 60) {
                        // Token is expired but within refresh window, continue with refresh
                        return $this->refreshToken($token);
                    }
                }
            }
            throw JwtException::tokenRefreshFailed(['reason' => 'expired_outside_refresh_window']);
        } catch (\Exception $e) {
            throw JwtException::tokenRefreshFailed(['error' => $e->getMessage()]);
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
        try {
            $tokenHash = hash('sha256', $token);
            $ttl = $this->refreshTtl * 60; // Convert minutes to seconds
            Cache::put("blacklisted_token:{$tokenHash}", true, $ttl);

            \Log::debug('Token blacklisted', [
                'token_hash' => $tokenHash,
                'ttl_seconds' => $ttl
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to blacklist token', [
                'error' => $e->getMessage(),
                'token_hash' => hash('sha256', $token)
            ]);
            throw $e;
        }
    }

    /**
     * Check if token is blacklisted
     */
    private function isTokenBlacklisted(string $token): bool
    {
        try {
            $tokenHash = hash('sha256', $token);
            return Cache::has("blacklisted_token:{$tokenHash}");
        } catch (\Exception $e) {
            \Log::error('Failed to check token blacklist status', [
                'error' => $e->getMessage(),
                'token_hash' => hash('sha256', $token)
            ]);
            // On error, assume token is not blacklisted to avoid false positives
            return false;
        }
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

    /**
     * Get token payload without validation (for debugging/inspection)
     */
    public function getTokenPayload(string $token): ?array
    {
        try {
            $parts = explode('.', $token);
            if (count($parts) !== 3) {
                return null;
            }

            $payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $parts[1])), true);
            return $payload ?: null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Check if token is expired without throwing exceptions
     */
    public function isTokenExpired(string $token): bool
    {
        try {
            $payload = $this->getTokenPayload($token);
            return $payload && isset($payload['exp']) && $payload['exp'] < time();
        } catch (\Exception $e) {
            return true; // Assume expired on error
        }
    }

    /**
     * Get token expiration time
     */
    public function getTokenExpiration(string $token): ?int
    {
        try {
            $payload = $this->getTokenPayload($token);
            return $payload['exp'] ?? null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get remaining token lifetime in seconds
     */
    public function getTokenRemainingLifetime(string $token): int
    {
        try {
            $exp = $this->getTokenExpiration($token);
            return $exp ? max(0, $exp - time()) : 0;
        } catch (\Exception $e) {
            return 0;
        }
    }
}
