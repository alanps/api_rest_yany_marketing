<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

uses(RefreshDatabase::class);

describe('registration', function () {
    test('creates a user and returns a token for valid data', function () {
        $response = $this->postJson('/api/register', [
            'name' => 'Ana Silva',
            'email' => 'ana@example.com',
            'password' => 'senha-segura-123',
            'password_confirmation' => 'senha-segura-123',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('user.name', 'Ana Silva')
            ->assertJsonPath('user.email', 'ana@example.com')
            ->assertJsonMissingPath('user.password')
            ->assertJsonStructure(['user' => ['id'], 'token']);

        $this->assertDatabaseHas('users', ['email' => 'ana@example.com']);
        $this->assertDatabaseCount('personal_access_tokens', 1);
    });

    test('returns 422 when the email is already registered', function () {
        User::factory()->create(['email' => 'ana@example.com']);

        $response = $this->postJson('/api/register', [
            'name' => 'Ana Silva',
            'email' => 'ana@example.com',
            'password' => 'senha-segura-123',
            'password_confirmation' => 'senha-segura-123',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    });

    test('returns 422 when required registration fields are missing', function () {
        $response = $this->postJson('/api/register', []);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email', 'password']);

        $this->assertDatabaseCount('users', 0);
    });

    test('returns 422 when registration fields are invalid', function () {
        $response = $this->postJson('/api/register', [
            'name' => str_repeat('a', 256),
            'email' => 'not-an-email',
            'password' => 'short',
            'password_confirmation' => 'does-not-match',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email', 'password']);

        $this->assertDatabaseCount('users', 0);
    });
});

describe('login', function () {
    test('returns a token and the user for valid credentials', function () {
        $user = User::factory()->create([
            'email' => 'ana@example.com',
            'password' => 'senha-segura-123',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'ana@example.com',
            'password' => 'senha-segura-123',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonMissingPath('user.password')
            ->assertJsonStructure(['user' => ['id', 'email'], 'token']);

        $this->assertDatabaseCount('personal_access_tokens', 1);
    });

    test('returns 401 when the password is incorrect', function () {
        User::factory()->create([
            'email' => 'ana@example.com',
            'password' => 'senha-segura-123',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'ana@example.com',
            'password' => 'senha-incorreta',
        ]);

        $response
            ->assertUnauthorized()
            ->assertJsonValidationErrors('email');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    });

    test('returns 401 when the user does not exist', function () {
        $response = $this->postJson('/api/login', [
            'email' => 'inexistente@example.com',
            'password' => 'senha-segura-123',
        ]);

        $response
            ->assertUnauthorized()
            ->assertJsonValidationErrors('email');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    });

    test('returns 422 when login fields are missing', function () {
        $response = $this->postJson('/api/login', []);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    });

    test('returns 422 when the login email is invalid', function () {
        $response = $this->postJson('/api/login', [
            'email' => 'not-an-email',
            'password' => 'senha-segura-123',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('email');
    });
});

describe('protected routes', function () {
    test('returns 401 when the user has no token', function () {
        $this->getJson('/api/me')->assertUnauthorized();
    });

    test('returns 401 when the token is invalid', function () {
        $this->withToken('token-invalido')->getJson('/api/me')->assertUnauthorized();
    });

    test('returns the authenticated user when the token is valid', function () {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('id', $user->id)
            ->assertJsonPath('email', $user->email)
            ->assertJsonMissingPath('password');
    });
});

describe('logout', function () {
    test('revokes the current token and prevents its reuse', function () {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/logout')
            ->assertOk()
            ->assertJsonPath('message', 'Logout realizado com sucesso.');

        $this->assertDatabaseCount('personal_access_tokens', 0);

        Auth::forgetGuards();

        $this->withToken($token)
            ->getJson('/api/me')
            ->assertUnauthorized();
    });
});
