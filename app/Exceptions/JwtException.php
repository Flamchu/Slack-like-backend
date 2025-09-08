<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JwtException extends Exception
{
    protected array $context = [];
    protected string $errorCode;

    public function __construct(string $message, int $code = 401, string $errorCode = '', array $context = [])
    {
        parent::__construct($message, $code);
        $this->errorCode = $errorCode;
        $this->context = $context;
    }

    public static function tokenExpired(array $context = []): self
    {
        return new self(
            'JWT token has expired',
            401,
            'TOKEN_EXPIRED',
            $context
        );
    }

    public static function tokenInvalid(array $context = []): self
    {
        return new self(
            'JWT token is invalid',
            401,
            'TOKEN_INVALID',
            $context
        );
    }

    public static function tokenNotProvided(array $context = []): self
    {
        return new self(
            'JWT token not provided',
            401,
            'TOKEN_NOT_PROVIDED',
            $context
        );
    }

    public static function tokenBlacklisted(array $context = []): self
    {
        return new self(
            'JWT token has been blacklisted',
            401,
            'TOKEN_BLACKLISTED',
            $context
        );
    }

    public static function tokenMalformed(array $context = []): self
    {
        return new self(
            'JWT token is malformed',
            401,
            'TOKEN_MALFORMED',
            $context
        );
    }

    public static function tokenRefreshFailed(array $context = []): self
    {
        return new self(
            'JWT token refresh failed',
            401,
            'TOKEN_REFRESH_FAILED',
            $context
        );
    }

    public static function userNotFound(array $context = []): self
    {
        return new self(
            'User not found for token',
            401,
            'USER_NOT_FOUND',
            $context
        );
    }

    public static function userInactive(array $context = []): self
    {
        return new self(
            'User account is inactive',
            403,
            'USER_INACTIVE',
            $context
        );
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Render the exception as an HTTP response.
     */
    public function render(Request $request): JsonResponse
    {
        $response = [
            'error' => 'Authentication Error',
            'message' => $this->getMessage(),
            'code' => $this->getErrorCode(),
        ];

        // add timestamp
        $response['timestamp'] = now()->toISOString();

        return response()->json($response, $this->getCode());
    }
}
