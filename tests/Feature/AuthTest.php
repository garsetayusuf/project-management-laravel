<?php

use App\Models\User;
use App\Services\JWTService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** REGISTRATION TESTS */
it('user can register with valid credentials', function () {
    /** @var Tests\TestCase $this */
    $response = $this->postJson('/api/register', [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertCreated();
    $response->assertJsonStructure([
        'status',
        'message',
        'statusCode',
        'data' => [
            'user' => ['id', 'name', 'email', 'created_at', 'updated_at'],
            'accessToken',
            'refreshToken',
        ],
    ]);
    expect(User::where('email', 'john@example.com')->exists())->toBeTrue();
});

it('user cannot register with mismatched passwords', function () {
    /** @var Tests\TestCase $this */
    $response = $this->postJson('/api/register', [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'password123',
        'password_confirmation' => 'different_password',
    ]);

    $response->assertUnprocessable();
    expect($response->json('data.errors.password'))->not->toBeEmpty();
    expect(User::where('email', 'john@example.com')->exists())->toBeFalse();
});

it('user cannot register with duplicate email', function () {
    /** @var Tests\TestCase $this */
    User::factory()->create(['email' => 'john@example.com']);

    $response = $this->postJson('/api/register', [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertUnprocessable();
    expect($response->json('data.errors.email'))->not->toBeEmpty();
});

it('user cannot register with short password', function () {
    /** @var Tests\TestCase $this */
    $response = $this->postJson('/api/register', [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'short',
        'password_confirmation' => 'short',
    ]);

    $response->assertUnprocessable();
    expect($response->json('data.errors.password'))->not->toBeEmpty();
});

it('user cannot register without required fields', function () {
    /** @var Tests\TestCase $this */
    $response = $this->postJson('/api/register', []);

    $response->assertUnprocessable();
    expect($response->json('data.errors'))->toHaveKeys(['name', 'email', 'password']);
});

it('registered user receives valid JWT token', function () {
    /** @var Tests\TestCase $this */
    $response = $this->postJson('/api/register', [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $accessToken = $response->json('data.accessToken');
    expect($accessToken)->not->toBeEmpty();

    $decoded = jwtService()->validateToken($accessToken);
    expect($decoded)->not->toBeNull();
    expect($decoded->email)->toBe('john@example.com');
    expect($decoded->type)->toBe('access');
});

/** LOGIN TESTS */
it('user can login with correct credentials', function () {
    $user = User::factory()->create([
        'email' => 'john@example.com',
        'password' => 'password123',
    ]);

    /** @var Tests\TestCase $this */
    $response = $this->postJson('/api/login', [
        'email' => 'john@example.com',
        'password' => 'password123',
    ]);

    $response->assertOk();
    $response->assertJsonStructure([
        'status',
        'message',
        'statusCode',
        'data' => [
            'user' => ['id', 'name', 'email'],
            'accessToken',
            'refreshToken',
        ],
    ]);
    expect($response->json('data.user.id'))->toBe($user->id);
});

it('user cannot login with wrong password', function () {
    User::factory()->create([
        'email' => 'john@example.com',
        'password' => 'password123',
    ]);

    /** @var Tests\TestCase $this */
    $response = $this->postJson('/api/login', [
        'email' => 'john@example.com',
        'password' => 'wrong_password',
    ]);

    $response->assertUnprocessable();
    $response->assertJsonStructure(['status', 'message', 'statusCode', 'data']);
});

it('user cannot login with non-existent email', function () {
    /** @var Tests\TestCase $this */
    $response = $this->postJson('/api/login', [
        'email' => 'nonexistent@example.com',
        'password' => 'password123',
    ]);

    $response->assertUnprocessable();
    $response->assertJsonStructure(['status', 'message', 'statusCode', 'data']);
});

it('user cannot login without required fields', function () {
    /** @var Tests\TestCase $this */
    $response = $this->postJson('/api/login', []);

    $response->assertUnprocessable();
    expect($response->json('data.errors'))->toHaveKeys(['email', 'password']);
});

it('login returns valid JWT token', function () {
    User::factory()->create([
        'email' => 'john@example.com',
        'password' => 'password123',
    ]);

    /** @var Tests\TestCase $this */
    $response = $this->postJson('/api/login', [
        'email' => 'john@example.com',
        'password' => 'password123',
    ]);

    $accessToken = $response->json('data.accessToken');
    expect($accessToken)->not->toBeEmpty();

    $decoded = jwtService()->validateToken($accessToken);
    expect($decoded)->not->toBeNull();
    expect($decoded->email)->toBe('john@example.com');
    expect($decoded->type)->toBe('access');
});

/** LOGOUT TESTS */
it('authenticated user can logout', function () {
    /** @var Tests\TestCase $this */
    $user = User::factory()->create();
    $token = jwtService()->generateAccessToken($user);

    $response = $this->postJson('/api/logout', [], [
        'Authorization' => "Bearer $token",
    ]);

    $response->assertOk();
    $response->assertJsonPath('message', 'Logged out successfully');
    $response->assertJsonStructure(['status', 'message', 'statusCode', 'data']);
});

it('unauthenticated user cannot logout', function () {
    /** @var Tests\TestCase $this */
    $response = $this->postJson('/api/logout', []);

    $response->assertUnauthorized();
});

/** GET USER TESTS */
it('authenticated user can get their info', function () {
    /** @var Tests\TestCase $this */
    $user = User::factory()->create();
    $token = jwtService()->generateAccessToken($user);

    $response = $this->getJson('/api/user', [
        'Authorization' => "Bearer $token",
    ]);

    $response->assertOk();
    $response->assertJsonStructure([
        'status',
        'message',
        'statusCode',
        'data' => [
            'user' => ['id', 'name', 'email', 'created_at', 'updated_at'],
        ],
    ]);
    expect($response->json('data.user.id'))->toBe($user->id);
    expect($response->json('data.user.email'))->toBe($user->email);
});

it('unauthenticated user cannot get user info', function () {
    /** @var Tests\TestCase $this */
    $response = $this->getJson('/api/user');

    $response->assertUnauthorized();
});

it('user with invalid token cannot get user info', function () {
    /** @var Tests\TestCase $this */
    $response = $this->getJson('/api/user', [
        'Authorization' => 'Bearer invalid.token.here',
    ]);

    $response->assertUnauthorized();
});

it('user with expired token cannot get user info', function () {
    /** @var Tests\TestCase $this */
    $user = User::factory()->create();

    // Create an expired token
    $jwtService = app(JWTService::class);
    $now = time();
    $payload = [
        'user_id' => $user->id,
        'email' => $user->email,
        'iat' => $now - 100000, // issued long ago
        'exp' => $now - 1000,    // expired long ago
    ];
    $expiredToken = \Firebase\JWT\JWT::encode($payload, config('jwt.secret'), config('jwt.algo', 'HS256'));

    $response = $this->getJson('/api/user', [
        'Authorization' => "Bearer $expiredToken",
    ]);

    $response->assertUnauthorized();
});

/** PASSWORD HASHING TESTS */
it('password is hashed on registration', function () {
    /** @var Tests\TestCase $this */
    $this->postJson('/api/register', [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $user = User::where('email', 'john@example.com')->first();
    expect($user->password)->not->toBe('password123');
    expect(\Illuminate\Support\Facades\Hash::check('password123', $user->password))->toBeTrue();
});

it('password is hashed correctly with bcrypt', function () {
    User::factory()->create([
        'email' => 'john@example.com',
        'password' => 'password123',
    ]);

    $user = User::where('email', 'john@example.com')->first();
    expect(\Illuminate\Support\Facades\Hash::check('password123', $user->password))->toBeTrue();
});
