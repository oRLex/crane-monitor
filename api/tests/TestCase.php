<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Testing\TestResponse;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public const ADMIN = 'admin@demo.io';

    public const OPERATOR = 'operator@demo.io';

    public const MANAGER = 'manager@budinvest.demo';

    public const VIEWER = 'viewer@skyline.demo';

    /** Issues a real Sanctum token the way the web app does, so abilities are exercised too. */
    protected function tokenFor(string $email, ?array $abilities = null): string
    {
        $payload = ['email' => $email] + ($abilities === null ? [] : ['abilities' => $abilities]);

        return $this->postJson('/api/auth/login', $payload)->assertOk()->json('token');
    }

    protected function as(string $email, ?array $abilities = null): static
    {
        $token = $this->tokenFor($email, $abilities);
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }

    protected function user(string $email): User
    {
        return User::query()->where('email', $email)->firstOrFail();
    }

    protected function getAs(string $email, string $uri): TestResponse
    {
        return $this->as($email)->getJson($uri);
    }
}
