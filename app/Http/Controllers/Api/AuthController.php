<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TokenBlacklist;
use App\Models\User;
use App\Services\JWTService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * @OA\Info(
 *     title="Project Managemenet API",
 *     version="1.0.0",
 *     description="API Documentation for Project Managemenet Authentication",
 *
 *     @OA\Contact(
 *         email="admin@example.com"
 *     )
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT"
 * )
 */
class AuthController extends Controller
{
    protected JWTService $jwtService;

    public function __construct(JWTService $jwtService)
    {
        $this->jwtService = $jwtService;
    }

    /**
     * Register a new user
     *
     * @OA\Post(
     *     path="/api/register",
     *     operationId="register",
     *     tags={"Authentication"},
     *     summary="Register a new user",
     *     description="Create a new user account and receive JWT token",
     *
     *     @OA\RequestBody(
     *         required=true,
     *         description="User registration credentials",
     *
     *         @OA\JsonContent(
     *             required={"name","email","password","password_confirmation"},
     *
     *             @OA\Property(property="name", type="string", example="John Doe", description="User full name"),
     *             @OA\Property(property="email", type="string", format="email", example="john@example.com", description="User email address"),
     *             @OA\Property(property="password", type="string", format="password", example="password123", description="Password (min 8 characters)"),
     *             @OA\Property(property="password_confirmation", type="string", format="password", example="password123", description="Password confirmation - must match password field")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="User registered successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="User registered successfully"),
     *             @OA\Property(property="user", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="John Doe"),
     *                 @OA\Property(property="email", type="string", example="john@example.com"),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time")
     *             ),
     *             @OA\Property(property="access_token", type="string", example="eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...", description="JWT access token (15 minute expiry)"),
     *             @OA\Property(property="refresh_token", type="string", example="abc123def456...", description="Refresh token (30 day expiry)"),
     *             @OA\Property(property="expires_in", type="integer", example=900, description="Access token expiry time in seconds")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     )
     * )
     */
    public function register(Request $request): JsonResponse
    {
        $request->validate(rules: [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::create(attributes: [
            'name' => $request->name,
            'email' => $request->email,
            'password' => $request->password,
        ]);

        $accessToken = $this->jwtService->generateAccessToken(user: $user);
        $refreshTokenData = $this->jwtService->generateRefreshToken(user: $user, request: $request);

        return response()->json(data: [
            'message' => 'User registered successfully',
            'user' => $user,
            'access_token' => $accessToken,
            'refresh_token' => $refreshTokenData['token'],
            'expires_in' => config('jwt.access_ttl', 15) * 60,
        ], status: 201);
    }

    /**
     * Login user
     *
     * @OA\Post(
     *     path="/api/login",
     *     operationId="login",
     *     tags={"Authentication"},
     *     summary="Login user",
     *     description="Authenticate user and receive JWT token",
     *
     *     @OA\RequestBody(
     *         required=true,
     *         description="User login credentials",
     *
     *         @OA\JsonContent(
     *             required={"email","password"},
     *
     *             @OA\Property(property="email", type="string", format="email", example="john@example.com"),
     *             @OA\Property(property="password", type="string", format="password", example="password123")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Login successful",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Login successful"),
     *             @OA\Property(property="user", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="John Doe"),
     *                 @OA\Property(property="email", type="string", example="john@example.com"),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time")
     *             ),
     *             @OA\Property(property="access_token", type="string", example="eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...", description="JWT access token (15 minute expiry)"),
     *             @OA\Property(property="refresh_token", type="string", example="abc123def456...", description="Refresh token (30 day expiry)"),
     *             @OA\Property(property="expires_in", type="integer", example=900, description="Access token expiry time in seconds")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Invalid credentials"
     *     )
     * )
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate(rules: [
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where(column: 'email', operator: $request->email)->first();

        if (! $user || ! Hash::check(value: $request->password, hashedValue: $user->password)) {
            throw ValidationException::withMessages(messages: [
                'credential' => ['The provided credentials are incorrect.'],
            ]);
        }

        $accessToken = $this->jwtService->generateAccessToken(user: $user);
        $refreshTokenData = $this->jwtService->generateRefreshToken(user: $user, request: $request);

        return response()->json(data: [
            'message' => 'Login successful',
            'user' => $user,
            'access_token' => $accessToken,
            'refresh_token' => $refreshTokenData['token'],
            'expires_in' => config('jwt.access_ttl', 15) * 60,
        ]);
    }

    /**
     * Logout user
     *
     * @OA\Post(
     *     path="/api/logout",
     *     operationId="logout",
     *     tags={"Authentication"},
     *     summary="Logout user",
     *     description="Logout authenticated user and revoke refresh token",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="refresh_token", type="string", example="a1b2c3d4...")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Logout successful",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Logged out successfully")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized - missing or invalid token"
     *     )
     * )
     */
    public function logout(Request $request): JsonResponse
    {
        $authHeader = $request->header('Authorization');
        if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
            $accessToken = substr($authHeader, 7);

            $decoded = $this->jwtService->validateToken($accessToken);
            if ($decoded && isset($decoded->exp)) {
                TokenBlacklist::blacklist(
                    $accessToken,
                    Carbon::createFromTimestamp($decoded->exp)
                );
            }
        }

        if ($request->has('refresh_token')) {
            $refreshToken = $this->jwtService->validateRefreshToken(token: $request->refresh_token);

            if ($refreshToken) {
                $refreshToken->revoke();
            }
        } else {
            $request->user()->revokeAllRefreshTokens();
        }

        return response()->json(data: [
            'message' => 'Logged out successfully',
        ]);
    }

    /**
     * Get authenticated user
     *
     * @OA\Get(
     *     path="/api/user",
     *     operationId="getUser",
     *     tags={"Authentication"},
     *     summary="Get authenticated user",
     *     description="Retrieve information of the authenticated user",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Response(
     *         response=200,
     *         description="User information retrieved",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="user", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="John Doe"),
     *                 @OA\Property(property="email", type="string", example="john@example.com"),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized - missing or invalid token"
     *     )
     * )
     */
    public function user(Request $request): JsonResponse
    {
        return response()->json(data: [
            'user' => $request->user(),
        ]);
    }

    /**
     * Refresh access token
     *
     * @OA\Post(
     *     path="/api/refresh",
     *     operationId="refresh",
     *     tags={"Authentication"},
     *     summary="Refresh access token",
     *     description="Get new access token and refresh token using valid refresh token",
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"refresh_token"},
     *
     *             @OA\Property(property="refresh_token", type="string", example="a1b2c3d4...")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Token refreshed successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="access_token", type="string"),
     *             @OA\Property(property="refresh_token", type="string"),
     *             @OA\Property(property="expires_in", type="integer")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Invalid or expired refresh token"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function refresh(Request $request): JsonResponse
    {
        $request->validate(rules: [
            'refresh_token' => 'required|string',
        ]);

        $refreshToken = $this->jwtService->validateRefreshToken(token: $request->refresh_token);

        if (! $refreshToken) {
            return response()->json(data: [
                'message' => 'Invalid or expired refresh token',
            ], status: 401);
        }

        $user = $refreshToken->user;

        $accessToken = $this->jwtService->generateAccessToken(user: $user);

        if (config('jwt.rotate_refresh_tokens', true)) {
            $refreshToken->revoke();
            $newRefreshTokenData = $this->jwtService->generateRefreshToken(user: $user, request: $request);
            $newRefreshToken = $newRefreshTokenData['token'];
        } else {
            $refreshToken->updateLastUsed();
            $newRefreshToken = $request->refresh_token;
        }

        return response()->json(data: [
            'access_token' => $accessToken,
            'refresh_token' => $newRefreshToken,
            'expires_in' => config('jwt.access_ttl', 15) * 60,
        ]);
    }

    /**
     * Logout from all devices
     *
     * @OA\Post(
     *     path="/api/logout/all",
     *     operationId="logoutAll",
     *     tags={"Authentication"},
     *     summary="Logout from all devices",
     *     description="Logout authenticated user from all devices and revoke all refresh tokens",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Logout successful",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Logged out from all devices"),
     *             @OA\Property(property="revoked_count", type="integer", example=3)
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized - missing or invalid token"
     *     )
     * )
     */
    public function logoutAll(Request $request): JsonResponse
    {
        $count = $request->user()->revokeAllRefreshTokens();

        return response()->json(data: [
            'message' => 'Logged out from all devices',
            'revoked_count' => $count,
        ]);
    }
}
