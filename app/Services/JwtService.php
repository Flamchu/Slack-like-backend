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
     * generate JWT token for user
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
     * validate JWT token
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
     * get user from token
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
            throw $e;
        } catch (\Exception $e) {
            throw JwtException::tokenMalformed(['error' => $e->getMessage()]);
        }
    }

    /**
     * refresh JWT token
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

            // validate token age for refresh window
            $tokenAge = time() - $payload->iat;
            if ($tokenAge > $this->refreshTtl * 60) {
                throw JwtException::tokenRefreshFailed(['reason' => 'token_too_old', 'age_seconds' => $tokenAge]);
            }

            // blacklist old token
            $this->blacklistToken($token);

            // create new token
            $newToken = $this->generateToken($user);

            // log refresh activity
            \Log::info('JWT token refreshed', [
                'user_id' => $user->id,
                'old_token_hash' => hash('sha256', $token),
                'new_token_hash' => hash('sha256', $newToken)
            ]);

            return $newToken;
        } catch (JwtException $e) {
            throw $e;
        } catch (ExpiredException $e) {
            // check if token is within refresh window
            $parts = explode('.', $token);
            if (count($parts) === 3) {
                $payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $parts[1])));
                if ($payload && isset($payload->iat)) {
                    $tokenAge = time() - $payload->iat;
                    if ($tokenAge <= $this->refreshTtl * 60) {
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
     * invalidate token (blacklist)
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
     * decode JWT token
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
     * blacklist a token
     */
    private function blacklistToken(string $token): void
    {
        try {
            $tokenHash = hash('sha256', $token);
            $ttl = $this->refreshTtl * 60; // convert minutes to seconds
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
     * check if token is blacklisted
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
            // on error, assume token is not blacklisted
            return false;
        }
    }

    /**
     * extract token from request header
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
     * get token payload without validation
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
     * check if token is expired without throwing exceptions
     */
    public function isTokenExpired(string $token): bool
    {
        try {
            $payload = $this->getTokenPayload($token);
            return $payload && isset($payload['exp']) && $payload['exp'] < time();
        } catch (\Exception $e) {
            return true; // assume expired on error
        }
    }

    /**
     * get token expiration time
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
     * get remaining token lifetime in seconds
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
