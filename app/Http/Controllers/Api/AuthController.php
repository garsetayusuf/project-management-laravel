<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RefreshTokenRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Traits\ApiResponse;
use App\Models\TokenBlacklist;
use App\Models\User;
use App\Services\JWTService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
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
    use ApiResponse;

    public function __construct(protected JWTService $jwtService) {}

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
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="User registered successfully"),
     *             @OA\Property(property="statusCode", type="integer", example=201),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="user", type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="John Doe"),
     *                     @OA\Property(property="email", type="string", example="john@example.com"),
     *                     @OA\Property(property="created_at", type="string", format="date-time"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time")
     *                 ),
     *                 @OA\Property(property="accessToken", type="string", example="eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...", description="JWT access token (15 minute expiry)"),
     *                 @OA\Property(property="refreshToken", type="string", example="abc123def456...", description="Refresh token (30 day expiry)")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="message", type="string", example="Validation failed"),
     *             @OA\Property(property="error", type="string", example="ValidationError"),
     *             @OA\Property(property="statusCode", type="integer", example=422),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create(attributes: [
            'name' => $request->name,
            'email' => $request->email,
            'password' => $request->password,
        ]);

        $accessToken = $this->jwtService->generateAccessToken(user: $user);
        $refreshTokenData = $this->jwtService->generateRefreshToken(user: $user, request: $request);

        return $this->sendSuccess(data: [
            'accessToken' => $accessToken,
            'refreshToken' => $refreshTokenData['token'],
            'user' => $user,
        ], message: 'User registered successfully', statusCode: 201);
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
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Login successful"),
     *             @OA\Property(property="statusCode", type="integer", example=200),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="user", type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="John Doe"),
     *                     @OA\Property(property="email", type="string", example="john@example.com"),
     *                     @OA\Property(property="created_at", type="string", format="date-time"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time")
     *                 ),
     *                 @OA\Property(property="accessToken", type="string", example="eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...", description="JWT access token (15 minute expiry)"),
     *                 @OA\Property(property="refreshToken", type="string", example="abc123def456...", description="Refresh token (30 day expiry)")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Invalid credentials",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="message", type="string", example="The provided credentials are incorrect."),
     *             @OA\Property(property="error", type="string", example="Error"),
     *             @OA\Property(property="statusCode", type="integer", example=422),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where(column: 'email', operator: $request->email)->first();

        if (! $user || ! Hash::check(value: $request->password, hashedValue: $user->password)) {
            return $this->sendError(message: 'The provided credentials are incorrect.', statusCode: 422);
        }

        $accessToken = $this->jwtService->generateAccessToken(user: $user);
        $refreshTokenData = $this->jwtService->generateRefreshToken(user: $user, request: $request);

        return $this->sendSuccess(data: [
            'accessToken' => $accessToken,
            'refreshToken' => $refreshTokenData['token'],
            'user' => $user,
        ], message: 'Login successful');
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
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Logged out successfully"),
     *             @OA\Property(property="statusCode", type="integer", example=200),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized - missing or invalid token",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="message", type="string", example="Token missing, invalid, or expired"),
     *             @OA\Property(property="error", type="string", example="Unauthorized"),
     *             @OA\Property(property="statusCode", type="integer", example=401),
     *             @OA\Property(property="data", type="object")
     *         )
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

        return $this->sendSuccess(message: 'Logged out successfully');
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
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="User retrieved successfully"),
     *             @OA\Property(property="statusCode", type="integer", example=200),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="user", type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="John Doe"),
     *                     @OA\Property(property="email", type="string", example="john@example.com"),
     *                     @OA\Property(property="created_at", type="string", format="date-time"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time")
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized - missing or invalid token",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="message", type="string", example="Token missing, invalid, or expired"),
     *             @OA\Property(property="error", type="string", example="Unauthorized"),
     *             @OA\Property(property="statusCode", type="integer", example=401),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     */
    public function user(Request $request): JsonResponse
    {
        return $this->sendSuccess(data: ['user' => $request->user()], message: 'User retrieved successfully');
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
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Token refreshed successfully"),
     *             @OA\Property(property="statusCode", type="integer", example=200),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="accessToken", type="string", example="eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..."),
     *                 @OA\Property(property="refreshToken", type="string", example="abc123def456...")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Invalid or expired refresh token",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="message", type="string", example="Invalid or expired refresh token"),
     *             @OA\Property(property="error", type="string", example="Error"),
     *             @OA\Property(property="statusCode", type="integer", example=401),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="message", type="string", example="Validation failed"),
     *             @OA\Property(property="error", type="string", example="ValidationError"),
     *             @OA\Property(property="statusCode", type="integer", example=422),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     */
    public function refresh(RefreshTokenRequest $request): JsonResponse
    {
        $refreshToken = $this->jwtService->validateRefreshToken(token: $request->refresh_token);

        if (! $refreshToken) {
            return $this->sendError(message: 'Invalid or expired refresh token', statusCode: 401);
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

        return $this->sendSuccess(data: [
            'accessToken' => $accessToken,
            'refreshToken' => $newRefreshToken,
        ], message: 'Token refreshed successfully');
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
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Logged out from all devices"),
     *             @OA\Property(property="statusCode", type="integer", example=200),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="revokedCount", type="integer", example=3)
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized - missing or invalid token",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="message", type="string", example="Token missing, invalid, or expired"),
     *             @OA\Property(property="error", type="string", example="Unauthorized"),
     *             @OA\Property(property="statusCode", type="integer", example=401),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     */
    public function logoutAll(Request $request): JsonResponse
    {
        $count = $request->user()->revokeAllRefreshTokens();

        return $this->sendSuccess(data: ['revokedCount' => $count], message: 'Logged out from all devices');
    }

    /**
     * Change user password
     *
     * @OA\Post(
     *     path="/api/change-password",
     *     operationId="changePassword",
     *     tags={"Authentication"},
     *     summary="Change user password",
     *     description="Change authenticated user's password",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *         description="Current and new password",
     *
     *         @OA\JsonContent(
     *             required={"current_password","password","password_confirmation"},
     *
     *             @OA\Property(property="current_password", type="string", format="password", example="oldpassword123", description="Current password"),
     *             @OA\Property(property="password", type="string", format="password", example="newpassword456", description="New password (min 8 characters)"),
     *             @OA\Property(property="password_confirmation", type="string", format="password", example="newpassword456", description="New password confirmation")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Password changed successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Password changed successfully"),
     *             @OA\Property(property="statusCode", type="integer", example=200),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized - missing or invalid token",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="message", type="string", example="Token missing, invalid, or expired"),
     *             @OA\Property(property="error", type="string", example="Unauthorized"),
     *             @OA\Property(property="statusCode", type="integer", example=401),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="message", type="string", example="Validation failed"),
     *             @OA\Property(property="error", type="string", example="ValidationError"),
     *             @OA\Property(property="statusCode", type="integer", example=422),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="errors", type="object",
     *                     @OA\Property(property="current_password", type="array", @OA\Items(type="string", example="The current password is incorrect.")),
     *                     @OA\Property(property="password", type="array", @OA\Items(type="string", example="The new password must be at least 8 characters."))
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();

        $user->update([
            'password' => $request->password,
        ]);

        return $this->sendSuccess(message: 'Password changed successfully');
    }
}
