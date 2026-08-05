<?php

namespace Tests\Feature\Api\V1;

use App\Models\Company;
use App\Models\User;
use App\Services\ApiTokenIssuer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class RateLimitTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Sanctum's guard memoizes the resolved user on the guard instance, which
     * — unlike a real request — survives across multiple `$this->getJson()`
     * calls within one test method. Forgetting the guard before each call
     * forces it to re-resolve from that call's own Authorization header,
     * matching what actually happens between two real, separate requests.
     */
    protected function asToken(string $token, string $uri): TestResponse
    {
        Auth::forgetGuards();

        return $this->withHeader('Authorization', "Bearer {$token}")->getJson($uri);
    }

    public function test_a_basic_plan_token_is_throttled_at_the_basic_tier(): void
    {
        $user = User::factory()->create();

        $company = Company::create([
            'slug' => 'acme',
            'name' => 'Acme Ltd',
            'owner_id' => $user->id,
            'currency' => 'USD',
            'plan' => 'basic',
            'account_type' => 'active',
        ]);

        $this->joinCompany($company, $user);

        $token = app(ApiTokenIssuer::class)->issue($user, $company, 'test', ['*'])->plainTextToken;

        for ($i = 0; $i < 60; $i++) {
            $this->asToken($token, '/api/v1/ping')->assertOk();
        }

        $response = $this->asToken($token, '/api/v1/ping');

        $response->assertStatus(429)->assertJsonPath('error.code', 'rate_limited');
        $this->assertNotNull($response->headers->get('Retry-After'));
    }

    public function test_two_tokens_for_the_same_user_have_independent_buckets(): void
    {
        $user = User::factory()->create();

        $company = Company::create([
            'slug' => 'acme',
            'name' => 'Acme Ltd',
            'owner_id' => $user->id,
            'currency' => 'USD',
            'plan' => 'basic',
            'account_type' => 'active',
        ]);

        $this->joinCompany($company, $user);

        $issuer = app(ApiTokenIssuer::class);
        $tokenA = $issuer->issue($user, $company, 'a', ['*'])->plainTextToken;
        $tokenB = $issuer->issue($user, $company, 'b', ['*'])->plainTextToken;

        for ($i = 0; $i < 60; $i++) {
            $this->asToken($tokenA, '/api/v1/ping')->assertOk();
        }

        $this->asToken($tokenA, '/api/v1/ping')->assertStatus(429);
        $this->asToken($tokenB, '/api/v1/ping')->assertOk();
    }
}
