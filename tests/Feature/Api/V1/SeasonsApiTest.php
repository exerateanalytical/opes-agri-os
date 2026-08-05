<?php

namespace Tests\Feature\Api\V1;

use App\Models\Company;
use App\Models\Role;
use App\Models\Season;
use App\Models\User;
use App\Services\ApiTokenIssuer;
use App\Support\CurrentCompany;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SeasonsApiTest extends TestCase
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

    public function test_it_creates_a_season(): void
    {
        $this->api()->postJson('/api/v1/seasons', [
            'name' => '2026 Long Rains',
            'starts_on' => '2026-03-01',
            'ends_on' => '2026-07-01',
        ])->assertCreated()->assertJsonPath('data.name', '2026 Long Rains');
    }

    public function test_a_season_name_must_be_unique_per_company(): void
    {
        app(CurrentCompany::class)->set($this->company);
        Season::create(['name' => '2026 Long Rains', 'starts_on' => '2026-03-01']);

        $this->api()->postJson('/api/v1/seasons', ['name' => '2026 Long Rains', 'starts_on' => '2026-03-01'])
            ->assertStatus(422)
            ->assertJsonPath('error.details.name.0', 'The name has already been taken.');
    }

    public function test_the_same_season_name_can_repeat_across_companies(): void
    {
        $otherOwner = User::factory()->create();
        $otherCompany = Company::create([
            'slug' => 'other-'.Str::lower(Str::random(4)),
            'name' => 'Other Ltd',
            'owner_id' => $otherOwner->id,
            'currency' => 'USD',
        ]);
        app(CurrentCompany::class)->set($otherCompany);
        Season::create(['name' => '2026 Long Rains', 'starts_on' => '2026-03-01']);

        $this->api()->postJson('/api/v1/seasons', ['name' => '2026 Long Rains', 'starts_on' => '2026-03-01'])
            ->assertCreated();
    }

    public function test_it_updates_and_deletes_a_season(): void
    {
        app(CurrentCompany::class)->set($this->company);
        $season = Season::create(['name' => 'Old Season', 'starts_on' => '2026-01-01']);

        $this->api()->patchJson("/api/v1/seasons/{$season->id}", ['name' => 'New Season'])
            ->assertOk()->assertJsonPath('data.name', 'New Season');

        $this->api()->deleteJson("/api/v1/seasons/{$season->id}")->assertNoContent();
        $this->assertSame(0, Season::query()->where('id', $season->id)->count());
    }
}
