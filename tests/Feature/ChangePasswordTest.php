<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('authenticated user can change password with correct current password', function () {
    /** @var Tests\TestCase $this */
    [$user, $token] = createUserWithToken();

    // Set a known password
    $user->update(['password' => 'oldpassword123']);

    $response = $this->postJson('/api/change-password', [
        'current_password' => 'oldpassword123',
        'password' => 'newpassword456',
        'password_confirmation' => 'newpassword456',
    ], [
        'Authorization' => "Bearer $token",
    ]);

    $response->assertOk();
    $response->assertJsonPath('message', 'Password changed successfully');

    // Verify password was actually changed
    $user->refresh();
    expect(Hash::check('newpassword456', $user->password))->toBeTrue();
    expect(Hash::check('oldpassword123', $user->password))->toBeFalse();
});

it('user can login with new password after change', function () {
    /** @var Tests\TestCase $this */
    $user = User::factory()->create(['password' => 'oldpassword123']);
    $token = jwtService()->generateAccessToken($user);

    $this->postJson('/api/change-password', [
        'current_password' => 'oldpassword123',
        'password' => 'newpassword456',
        'password_confirmation' => 'newpassword456',
    ], [
        'Authorization' => "Bearer $token",
    ]);

    // Try to login with new password
    $response = $this->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'newpassword456',
    ]);

    $response->assertOk();
    expect($response->json('data.accessToken'))->not->toBeEmpty();
});

it('user cannot login with old password after change', function () {
    /** @var Tests\TestCase $this */
    $user = User::factory()->create(['password' => 'oldpassword123']);
    $token = jwtService()->generateAccessToken($user);

    $this->postJson('/api/change-password', [
        'current_password' => 'oldpassword123',
        'password' => 'newpassword456',
        'password_confirmation' => 'newpassword456',
    ], [
        'Authorization' => "Bearer $token",
    ]);

    // Try to login with old password
    $response = $this->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'oldpassword123',
    ]);

    $response->assertUnprocessable();
});

it('cannot change password with incorrect current password', function () {
    /** @var Tests\TestCase $this */
    [$user, $token] = createUserWithToken();
    $user->update(['password' => 'correctpassword']);

    $response = $this->postJson('/api/change-password', [
        'current_password' => 'wrongpassword',
        'password' => 'newpassword456',
        'password_confirmation' => 'newpassword456',
    ], [
        'Authorization' => "Bearer $token",
    ]);

    $response->assertUnprocessable();
    expect($response->json('data.errors.current_password'))->not->toBeEmpty();

    // Verify password was NOT changed
    $user->refresh();
    expect(Hash::check('correctpassword', $user->password))->toBeTrue();
});

it('cannot change password without authentication', function () {
    /** @var Tests\TestCase $this */
    $response = $this->postJson('/api/change-password', [
        'current_password' => 'oldpassword123',
        'password' => 'newpassword456',
        'password_confirmation' => 'newpassword456',
    ]);

    $response->assertUnauthorized();
});

it('cannot change password without required fields', function () {
    /** @var Tests\TestCase $this */
    [$user, $token] = createUserWithToken();

    $response = $this->postJson('/api/change-password', [], [
        'Authorization' => "Bearer $token",
    ]);

    $response->assertUnprocessable();
    expect($response->json('data.errors'))->toHaveKeys(['current_password', 'password']);
});

it('cannot change password with short new password', function () {
    /** @var Tests\TestCase $this */
    [$user, $token] = createUserWithToken();
    $user->update(['password' => 'oldpassword123']);

    $response = $this->postJson('/api/change-password', [
        'current_password' => 'oldpassword123',
        'password' => 'short',
        'password_confirmation' => 'short',
    ], [
        'Authorization' => "Bearer $token",
    ]);

    $response->assertUnprocessable();
    expect($response->json('data.errors.password'))->not->toBeEmpty();
});

it('cannot change password when confirmation does not match', function () {
    /** @var Tests\TestCase $this */
    [$user, $token] = createUserWithToken();
    $user->update(['password' => 'oldpassword123']);

    $response = $this->postJson('/api/change-password', [
        'current_password' => 'oldpassword123',
        'password' => 'newpassword456',
        'password_confirmation' => 'differentpassword',
    ], [
        'Authorization' => "Bearer $token",
    ]);

    $response->assertUnprocessable();
    expect($response->json('data.errors.password'))->not->toBeEmpty();
});

it('password is properly hashed after change', function () {
    /** @var Tests\TestCase $this */
    [$user, $token] = createUserWithToken();
    $user->update(['password' => 'oldpassword123']);

    $this->postJson('/api/change-password', [
        'current_password' => 'oldpassword123',
        'password' => 'newpassword456',
        'password_confirmation' => 'newpassword456',
    ], [
        'Authorization' => "Bearer $token",
    ]);

    $user->refresh();

    // Password should not be stored in plain text
    expect($user->password)->not->toBe('newpassword456');

    // Password should be a valid bcrypt hash
    expect($user->password)->toStartWith('$2y$');

    // Password should verify correctly
    expect(Hash::check('newpassword456', $user->password))->toBeTrue();
});

it('cannot change password with invalid token', function () {
    /** @var Tests\TestCase $this */
    $response = $this->postJson('/api/change-password', [
        'current_password' => 'oldpassword123',
        'password' => 'newpassword456',
        'password_confirmation' => 'newpassword456',
    ], [
        'Authorization' => 'Bearer invalid.token.here',
    ]);

    $response->assertUnauthorized();
});

it('cannot change password with expired token', function () {
    /** @var Tests\TestCase $this */
    $user = User::factory()->create(['password' => 'oldpassword123']);

    // Create an expired token
    $now = time();
    $payload = [
        'user_id' => $user->id,
        'email' => $user->email,
        'iat' => $now - 100000,
        'exp' => $now - 1000,
    ];
    $expiredToken = \Firebase\JWT\JWT::encode($payload, config('jwt.secret'), config('jwt.algo', 'HS256'));

    $response = $this->postJson('/api/change-password', [
        'current_password' => 'oldpassword123',
        'password' => 'newpassword456',
        'password_confirmation' => 'newpassword456',
    ], [
        'Authorization' => "Bearer $expiredToken",
    ]);

    $response->assertUnauthorized();
});
