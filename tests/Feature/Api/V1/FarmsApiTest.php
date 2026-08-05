<?php

namespace Tests\Feature\Api\V1;

use App\Models\Company;
use App\Models\Farm;
use App\Models\Role;
use App\Models\User;
use App\Services\ApiTokenIssuer;
use App\Support\CurrentCompany;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FarmsApiTest extends TestCase
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

        $this->token = app(ApiTokenIssuer::class)
            ->issue($this->owner, $this->company, 'test', ['*'])
            ->plainTextToken;
    }

    protected function api()
    {
        return $this->withHeader('Authorization', "Bearer {$this->token}");
    }

    public function test_it_creates_a_farm(): void
    {
        $response = $this->api()->postJson('/api/v1/farms', [
            'name' => 'Green Valley Farm',
            'size_hectares' => 12.5,
            'ownership_type' => 'owned',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Green Valley Farm')
            ->assertJsonPath('data.size_hectares', '12.50');

        $farm = Farm::query()->where('company_id', $this->company->id)->sole();
        $this->assertSame($this->owner->id, $farm->created_by);
    }

    public function test_it_rejects_a_farm_with_no_name(): void
    {
        $this->api()->postJson('/api/v1/farms', [])
            ->assertStatus(422)
            ->assertJsonPath('error.details.name.0', 'The name field is required.');
    }

    public function test_it_lists_shows_updates_and_deletes_a_farm(): void
    {
        app(CurrentCompany::class)->set($this->company);
        $farm = Farm::create(['name' => 'Old Farm']);

        $this->api()->getJson('/api/v1/farms')->assertOk()->assertJsonPath('data.0.name', 'Old Farm');
        $this->api()->getJson("/api/v1/farms/{$farm->id}")->assertOk()->assertJsonPath('data.id', $farm->id);

        $this->api()->patchJson("/api/v1/farms/{$farm->id}", ['name' => 'New Farm'])
            ->assertOk()->assertJsonPath('data.name', 'New Farm');

        $this->api()->deleteJson("/api/v1/farms/{$farm->id}")->assertNoContent();
        $this->assertSame(0, Farm::query()->where('id', $farm->id)->count());
    }

    public function test_a_token_without_the_create_ability_is_refused(): void
    {
        $viewOnlyToken = app(ApiTokenIssuer::class)
            ->issue($this->owner, $this->company, 'view-only', ['farms.view'])
            ->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$viewOnlyToken}")
            ->postJson('/api/v1/farms', ['name' => 'Nope'])
            ->assertStatus(403);
    }

    public function test_a_farm_cannot_be_read_from_another_company(): void
    {
        $otherOwner = User::factory()->create();
        $otherCompany = Company::create([
            'slug' => 'other-'.Str::lower(Str::random(4)),
            'name' => 'Other Ltd',
            'owner_id' => $otherOwner->id,
            'currency' => 'USD',
        ]);
        app(CurrentCompany::class)->set($otherCompany);
        $theirFarm = Farm::create(['name' => 'Theirs']);

        $this->api()->getJson("/api/v1/farms/{$theirFarm->id}")->assertStatus(404);
    }
}
