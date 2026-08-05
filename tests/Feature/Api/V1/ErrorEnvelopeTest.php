<?php

namespace Tests\Feature\Api\V1;

use App\Models\Company;
use App\Models\User;
use App\Services\ApiTokenIssuer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ErrorEnvelopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_unauthenticated_request_gets_the_error_envelope(): void
    {
        $this->getJson('/api/v1/ping')
            ->assertStatus(401)
            ->assertJsonPath('error.type', 'authentication_error')
            ->assertJsonPath('error.code', 'unauthenticated')
            ->assertJsonMissingPath('error.details');
    }

    public function test_a_missing_route_gets_the_error_envelope(): void
    {
        $user = User::factory()->create();

        $company = Company::create([
            'slug' => 'acme',
            'name' => 'Acme Ltd',
            'owner_id' => $user->id,
            'currency' => 'USD',
        ]);

        $this->joinCompany($company, $user);

        $token = app(ApiTokenIssuer::class)->issue($user, $company, 'test', ['*'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/does-not-exist')
            ->assertStatus(404)
            ->assertJsonPath('error.type', 'invalid_request')
            ->assertJsonPath('error.code', 'not_found');
    }

    public function test_a_validation_failure_gets_the_error_envelope_with_details(): void
    {
        $user = User::factory()->create();

        $company = Company::create([
            'slug' => 'acme',
            'name' => 'Acme Ltd',
            'owner_id' => $user->id,
            'currency' => 'USD',
        ]);

        $this->joinCompany($company, $user);

        $token = app(ApiTokenIssuer::class)->issue($user, $company, 'test', ['*'])->plainTextToken;

        Route::prefix('api/v1')->middleware(['auth:sanctum', 'api.company', 'throttle:api'])->group(function () {
            Route::post('/_test/validate', function () {
                request()->validate(['name' => 'required|string']);

                return response()->json(['data' => ['ok' => true]]);
            });
        });

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/_test/validate', [])
            ->assertStatus(422)
            ->assertJsonPath('error.type', 'invalid_request')
            ->assertJsonPath('error.code', 'validation_failed')
            ->assertJsonPath('error.details.name.0', 'The name field is required.');
    }
}
