<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\JwtService;
use App\Services\ActivityLogService;
use App\Enums\UserRole;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    protected JwtService $jwtService;
    protected ActivityLogService $activityLogService;

    public function __construct(JwtService $jwtService, ActivityLogService $activityLogService)
    {
        $this->jwtService = $jwtService;
        $this->activityLogService = $activityLogService;
    }

    /**
     * register a new user
     */
    public function register(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'first_name' => 'required|string|max:255',
                'last_name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users',
                'password' => 'required|string|min:8|confirmed',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Validation failed',
                    'messages' => $validator->errors()
                ], 422);
            }

            $user = User::create([
                'name' => $request->first_name . ' ' . $request->last_name,
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => UserRole::USER->value,
                'is_active' => true,
            ]);

            $token = $this->jwtService->generateToken($user);

            // log registration
            $this->activityLogService->log(
                'user_registered',
                'User registered successfully',
                $user,
                null,
                ['ip' => $request->ip()],
                $request
            );

            return response()->json([
                'message' => 'User registered successfully',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                ],
                'access_token' => $token,
                'token_type' => 'Bearer',
                'expires_in' => config('jwt.ttl') * 60
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Registration failed',
                'message' => 'An error occurred during registration'
            ], 500);
        }
    }

    /**
     * login user and generate jwt token
     */
    public function login(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|string|email',
                'password' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Validation failed',
                    'messages' => $validator->errors()
                ], 422);
            }

            $user = User::where('email', $request->email)->first();

            if (!$user || !Hash::check($request->password, $user->password)) {
                return response()->json([
                    'error' => 'Unauthorized',
                    'message' => 'Invalid credentials'
                ], 401);
            }

            if (!$user->is_active) {
                return response()->json([
                    'error' => 'Forbidden',
                    'message' => 'Account is deactivated'
                ], 403);
            }

            // update last login
            $user->update(['last_login_at' => now()]);

            $token = $this->jwtService->generateToken($user);

            // log login
            $this->activityLogService->log(
                'user_login',
                'User logged in successfully',
                $user,
                null,
                ['ip' => $request->ip()],
                $request
            );

            return response()->json([
                'message' => 'Login successful',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                ],
                'access_token' => $token,
                'token_type' => 'Bearer',
                'expires_in' => config('jwt.ttl') * 60
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Login failed',
                'message' => 'An error occurred during login'
            ], 500);
        }
    }

    /**
     * logout user and invalidate token
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            // get token from header
            $authHeader = $request->header('Authorization');
            $token = null;

            if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
                $token = substr($authHeader, 7);
            }

            if ($token) {
                $this->jwtService->invalidateToken($token);
            }

            // log logout
            $user = $request->user();
            if ($user) {
                $this->activityLogService->log(
                    'user_logout',
                    'User logged out successfully',
                    $user,
                    null,
                    ['ip' => $request->ip()],
                    $request
                );
            }

            return response()->json([
                'message' => 'Logout successful'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Logout failed',
                'message' => 'An error occurred during logout'
            ], 500);
        }
    }

    /**
     * refresh jwt token
     */
    public function refresh(Request $request): JsonResponse
    {
        try {
            $token = $this->jwtService->getTokenFromRequest($request);

            if (!$token) {
                return response()->json([
                    'error' => 'Unauthorized',
                    'message' => 'Token not provided'
                ], 401);
            }

            $newToken = $this->jwtService->refreshToken($token);

            return response()->json([
                'access_token' => $newToken,
                'token_type' => 'Bearer',
                'expires_in' => config('jwt.ttl') * 60
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Token refresh failed',
                'message' => $e->getMessage()
            ], 401);
        }
    }

    /**
     * get authenticated user profile
     */
    public function profile(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            return response()->json([
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                    'avatar' => $user->avatar,
                    'role' => $user->role,
                    'is_active' => $user->is_active,
                    'last_login_at' => $user->last_login_at,
                    'created_at' => $user->created_at,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Profile fetch failed',
                'message' => 'An error occurred while fetching profile'
            ], 500);
        }
    }
}
