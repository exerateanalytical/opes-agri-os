<?php

namespace Tests\Unit\Http\Middleware;

use App\Models\Company;
use App\Models\User;
use App\Services\ApiTokenIssuer;
use App\Support\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResolveApiCompanyTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        $this->company = Company::create([
            'slug' => 'acme',
            'name' => 'Acme Ltd',
            'owner_id' => $this->user->id,
            'currency' => 'USD',
        ]);

        $this->joinCompany($this->company, $this->user);
    }

    protected function tokenFor(Company $company, array $abilities = ['*']): string
    {
        return app(ApiTokenIssuer::class)->issue($this->user, $company, 'test', $abilities)->plainTextToken;
    }

    public function test_a_valid_token_resolves_the_bound_company(): void
    {
        $token = $this->tokenFor($this->company);

        $response = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/ping');

        $response->assertOk()->assertJsonPath('data.company', 'acme');
    }

    public function test_a_token_cannot_be_issued_for_a_company_the_user_does_not_belong_to(): void
    {
        $otherCompany = Company::create([
            'slug' => 'other',
            'name' => 'Other Ltd',
            'owner_id' => User::factory()->create()->id,
            'currency' => 'USD',
        ]);

        $this->expectException(\RuntimeException::class);

        $this->tokenFor($otherCompany);
    }

    public function test_a_token_whose_user_no_longer_belongs_to_the_company_is_rejected(): void
    {
        $token = $this->tokenFor($this->company);

        $this->company->users()->updateExistingPivot($this->user->id, ['status' => 'inactive']);

        $response = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/ping');

        $response->assertStatus(403)->assertJsonPath('error.code', 'token_company_mismatch');
    }

    public function test_a_token_bound_to_a_suspended_company_is_rejected(): void
    {
        $token = $this->tokenFor($this->company);

        $this->company->forceFill(['suspended_at' => now()])->save();

        $response = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/ping');

        $response->assertStatus(403)->assertJsonPath('error.code', 'company_suspended');
    }

    public function test_it_sets_current_company_for_the_request(): void
    {
        $token = $this->tokenFor($this->company);

        $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/ping');

        $this->assertSame($this->company->id, app(CurrentCompany::class)->id());
    }

    public function test_a_request_with_no_token_is_unauthenticated(): void
    {
        $this->getJson('/api/v1/ping')->assertStatus(401);
    }
}
