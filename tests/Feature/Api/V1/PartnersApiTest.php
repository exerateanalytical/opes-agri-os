<?php

namespace Tests\Feature\Api\V1;

use App\Models\Company;
use App\Models\Partner;
use App\Models\Role;
use App\Models\User;
use App\Services\ApiTokenIssuer;
use App\Support\CurrentCompany;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Tests\TestCase;

class PartnersApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected Company $company;

    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->owner = User::factory()->create();
        $this->company = Company::create([
            'slug' => 'acme-'.Str::lower(Str::random(4)),
            'name' => 'Acme Ltd',
            'owner_id' => $this->owner->id,
            'currency' => 'USD',
        ]);

        $this->joinCompany($this->company, $this->owner, Role::OWNER);
        app(CurrentCompany::class)->set($this->company);

        $this->token = app(ApiTokenIssuer::class)
            ->issue($this->owner, $this->company, 'test', ['*'])
            ->plainTextToken;
    }

    protected function api()
    {
        return $this->withHeader('Authorization', "Bearer {$this->token}");
    }

    public function test_it_creates_a_partner(): void
    {
        $response = $this->api()->postJson('/api/v1/partners', [
            'name' => 'Green Future NGO',
            'type' => 'ngo',
            'email' => 'contact@greenfuture.org',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.type', 'ngo');
    }

    public function test_it_records_an_interaction(): void
    {
        app(CurrentCompany::class)->set($this->company);
        $partner = Partner::create(['name' => 'World Aid', 'type' => 'donor', 'status' => 'active']);

        $response = $this->api()->postJson("/api/v1/partners/{$partner->id}/interactions", [
            'interaction_date' => '2026-08-01',
            'type' => 'call',
            'summary' => 'Discussed Q3 funding.',
        ]);

        $response->assertCreated()->assertJsonPath('data.type', 'call');

        $this->api()->getJson("/api/v1/partners/{$partner->id}/interactions")
            ->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_a_token_without_the_record_interaction_ability_is_refused(): void
    {
        app(CurrentCompany::class)->set($this->company);
        $partner = Partner::create(['name' => 'World Aid', 'type' => 'donor', 'status' => 'active']);

        $limitedToken = app(ApiTokenIssuer::class)
            ->issue($this->owner, $this->company, 'view-only', ['partner-crm.view'])
            ->plainTextToken;

        Auth::forgetGuards();

        $this->withHeader('Authorization', "Bearer {$limitedToken}")
            ->postJson("/api/v1/partners/{$partner->id}/interactions", ['interaction_date' => '2026-08-01', 'type' => 'call', 'summary' => 'x'])
            ->assertStatus(403);
    }

    public function test_partners_are_scoped_to_their_company(): void
    {
        $otherOwner = User::factory()->create();
        $otherCompany = Company::create([
            'slug' => 'other-'.Str::lower(Str::random(4)),
            'name' => 'Other Ltd',
            'owner_id' => $otherOwner->id,
            'currency' => 'USD',
        ]);
        app(CurrentCompany::class)->set($otherCompany);
        $theirPartner = Partner::create(['name' => 'Their NGO', 'type' => 'ngo', 'status' => 'active']);

        $this->api()->getJson("/api/v1/partners/{$theirPartner->id}")->assertStatus(404);
    }
}
