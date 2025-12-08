<?php

use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** REFRESH ENDPOINT TESTS */
it('user can refresh access token with valid refresh token', function () {
    /** @var Tests\TestCase $this */
    $user = User::factory()->create();
    $refreshTokenData = jwtService()->generateRefreshToken($user);
    $refreshToken = $refreshTokenData['token'];

    $response = $this->postJson('/api/refresh', [
        'refresh_token' => $refreshToken,
    ]);

    $response->assertOk();
    $response->assertJsonStructure([
        'access_token',
        'refresh_token',
        'expires_in',
    ]);
    expect($response->json('access_token'))->not->toBeEmpty();
    expect($response->json('refresh_token'))->not->toBeEmpty();
});

it('user receives new refresh token on refresh with rotation enabled', function () {
    /** @var Tests\TestCase $this */
    config(['jwt.rotate_refresh_tokens' => true]);
    $user = User::factory()->create();
    $refreshTokenData = jwtService()->generateRefreshToken($user);
    $oldRefreshToken = $refreshTokenData['token'];

    $response = $this->postJson('/api/refresh', [
        'refresh_token' => $oldRefreshToken,
    ]);

    $newRefreshToken = $response->json('refresh_token');
    expect($newRefreshToken)->not->toBe($oldRefreshToken);
});

it('old refresh token is revoked after successful refresh with rotation', function () {
    /** @var Tests\TestCase $this */
    config(['jwt.rotate_refresh_tokens' => true]);
    $user = User::factory()->create();
    $refreshTokenData = jwtService()->generateRefreshToken($user);
    $oldRefreshToken = $refreshTokenData['token'];

    // Refresh token
    $response = $this->postJson('/api/refresh', [
        'refresh_token' => $oldRefreshToken,
    ]);
    $response->assertOk();

    // Try to use old token - should fail
    $response = $this->postJson('/api/refresh', [
        'refresh_token' => $oldRefreshToken,
    ]);

    $response->assertUnauthorized();
});

it('user cannot refresh with invalid refresh token', function () {
    /** @var Tests\TestCase $this */
    $response = $this->postJson('/api/refresh', [
        'refresh_token' => 'invalid_token_here',
    ]);

    $response->assertUnauthorized();
    $response->assertJsonPath('message', 'Invalid or expired refresh token');
});

it('user cannot refresh with expired refresh token', function () {
    /** @var Tests\TestCase $this */
    $user = User::factory()->create();

    // Create an expired refresh token
    $token = 'expired_token_'.\Illuminate\Support\Str::random(50);
    $tokenHash = hash('sha256', $token);
    RefreshToken::create([
        'user_id' => $user->id,
        'token_hash' => $tokenHash,
        'device_name' => 'Test Device',
        'ip_address' => '127.0.0.1',
        'expires_at' => now()->subDays(1),
    ]);

    $response = $this->postJson('/api/refresh', [
        'refresh_token' => $token,
    ]);

    $response->assertUnauthorized();
});

it('user cannot refresh without refresh token', function () {
    /** @var Tests\TestCase $this */
    $response = $this->postJson('/api/refresh', []);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors('refresh_token');
});

it('new access token has correct expiry and type', function () {
    /** @var Tests\TestCase $this */
    $user = User::factory()->create();
    $refreshTokenData = jwtService()->generateRefreshToken($user);
    $refreshToken = $refreshTokenData['token'];

    $response = $this->postJson('/api/refresh', [
        'refresh_token' => $refreshToken,
    ]);

    $accessToken = $response->json('access_token');
    $decoded = jwtService()->validateToken($accessToken);

    expect($decoded)->not->toBeNull();
    expect($decoded->type)->toBe('access');
    expect($decoded->user_id)->toBe($user->id);
});

/** LOGOUT TESTS */
it('logout revokes specific refresh token', function () {
    /** @var Tests\TestCase $this */
    $user = User::factory()->create();
    $refreshTokenData = jwtService()->generateRefreshToken($user);
    $refreshToken = $refreshTokenData['token'];
    $accessToken = jwtService()->generateAccessToken($user);

    // Logout with refresh token
    $response = $this->postJson('/api/logout', [
        'refresh_token' => $refreshToken,
    ], [
        'Authorization' => "Bearer $accessToken",
    ]);

    $response->assertOk();

    // Try to use revoked refresh token
    $response = $this->postJson('/api/refresh', [
        'refresh_token' => $refreshToken,
    ]);

    $response->assertUnauthorized();
});

it('logout all revokes all user refresh tokens', function () {
    /** @var Tests\TestCase $this */
    $user = User::factory()->create();

    // Create multiple refresh tokens
    $token1 = jwtService()->generateRefreshToken($user)['token'];
    $token2 = jwtService()->generateRefreshToken($user)['token'];
    $token3 = jwtService()->generateRefreshToken($user)['token'];

    $accessToken = jwtService()->generateAccessToken($user);

    // Logout from all devices
    $response = $this->postJson('/api/logout/all', [], [
        'Authorization' => "Bearer $accessToken",
    ]);

    $response->assertOk();
    $response->assertJsonPath('revoked_count', 3);

    // Try to use each revoked token
    foreach ([$token1, $token2, $token3] as $token) {
        $response = $this->postJson('/api/refresh', [
            'refresh_token' => $token,
        ]);
        $response->assertUnauthorized();
    }
});

/** REFRESH TOKEN STORAGE TESTS */
it('refresh token hash is stored in database', function () {
    /** @var Tests\TestCase $this */
    $user = User::factory()->create();
    $refreshTokenData = jwtService()->generateRefreshToken($user);
    $plainToken = $refreshTokenData['token'];

    $tokenHash = hash('sha256', $plainToken);

    $stored = RefreshToken::where('token_hash', $tokenHash)->first();
    expect($stored)->not->toBeNull();
    expect($stored->user_id)->toBe($user->id);
});

it('plain refresh token is never stored in database', function () {
    /** @var Tests\TestCase $this */
    $user = User::factory()->create();
    $refreshTokenData = jwtService()->generateRefreshToken($user);
    $plainToken = $refreshTokenData['token'];

    // Search for plain token in database - should not find it
    $stored = RefreshToken::where('token_hash', $plainToken)->first();
    expect($stored)->toBeNull();
});

it('refresh token stores device name from user agent', function () {
    /** @var Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'password',
    ], [
        'User-Agent' => 'Mozilla/5.0 Chrome/100.0',
    ]);

    $refreshToken = RefreshToken::latest()->first();
    expect($refreshToken->device_name)->toBe('Chrome Browser');
});

it('refresh token stores ip address', function () {
    /** @var Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'password',
    ], [
        'X-Forwarded-For' => '192.168.1.100',
    ]);

    $refreshToken = RefreshToken::latest()->first();
    expect($refreshToken->ip_address)->not->toBeNull();
});

/** REFRESH TOKEN EXPIRY TESTS */
it('refresh token is not valid after expiry date', function () {
    /** @var Tests\TestCase $this */
    $user = User::factory()->create();

    $token = 'expired_token_'.\Illuminate\Support\Str::random(50);
    $tokenHash = hash('sha256', $token);
    RefreshToken::create([
        'user_id' => $user->id,
        'token_hash' => $tokenHash,
        'device_name' => 'Test Device',
        'ip_address' => '127.0.0.1',
        'expires_at' => now()->subDays(1),
    ]);

    $validated = jwtService()->validateRefreshToken($token);
    expect($validated)->toBeNull();
});

it('refresh token is valid before expiry date', function () {
    /** @var Tests\TestCase $this */
    $user = User::factory()->create();

    $token = 'valid_token_'.\Illuminate\Support\Str::random(50);
    $tokenHash = hash('sha256', $token);
    RefreshToken::create([
        'user_id' => $user->id,
        'token_hash' => $tokenHash,
        'device_name' => 'Test Device',
        'ip_address' => '127.0.0.1',
        'expires_at' => now()->addDays(1),
    ]);

    $validated = jwtService()->validateRefreshToken($token);
    expect($validated)->not->toBeNull();
    expect($validated->user_id)->toBe($user->id);
});

/** MULTI-DEVICE SUPPORT TESTS */
it('user can have multiple refresh tokens for different devices', function () {
    /** @var Tests\TestCase $this */
    $user = User::factory()->create();

    $token1 = jwtService()->generateRefreshToken($user)['token'];
    $token2 = jwtService()->generateRefreshToken($user)['token'];
    $token3 = jwtService()->generateRefreshToken($user)['token'];

    expect(RefreshToken::where('user_id', $user->id)->count())->toBe(3);
});

it('revoking one token does not affect others', function () {
    /** @var Tests\TestCase $this */
    $user = User::factory()->create();

    $token1Data = jwtService()->generateRefreshToken($user);
    $token2Data = jwtService()->generateRefreshToken($user);

    $token1 = $token1Data['token'];
    $token2 = $token2Data['token'];

    // Revoke first token
    $refreshTokenModel = $token1Data['model'];
    $refreshTokenModel->revoke();

    // First token should be invalid
    $validated1 = jwtService()->validateRefreshToken($token1);
    expect($validated1)->toBeNull();

    // Second token should still be valid
    $validated2 = jwtService()->validateRefreshToken($token2);
    expect($validated2)->not->toBeNull();
});

/** ACCESS TOKEN BLACKLIST TESTS */
it('access token cannot be used after logout', function () {
    /** @var Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $accessToken = $response->json('access_token');

    // Verify token works initially
    $this->getJson('/api/user', [
        'Authorization' => "Bearer $accessToken",
    ])->assertOk();

    // Logout (this will blacklist the token)
    $this->postJson('/api/logout', [], [
        'Authorization' => "Bearer $accessToken",
    ])->assertOk();

    // Try to use the token again - should fail
    $this->getJson('/api/user', [
        'Authorization' => "Bearer $accessToken",
    ])->assertUnauthorized()
        ->assertJson(['message' => 'Unauthorized: Token has been revoked']);
});
