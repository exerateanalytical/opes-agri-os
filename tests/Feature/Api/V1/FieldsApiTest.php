<?php

namespace Tests\Feature\Api\V1;

use App\Models\Company;
use App\Models\Farm;
use App\Models\Field;
use App\Models\Role;
use App\Models\User;
use App\Services\ApiTokenIssuer;
use App\Support\CurrentCompany;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FieldsApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected Company $company;

    protected Farm $farm;

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
        $this->farm = Farm::create(['name' => 'Green Valley Farm']);

        $this->token = app(ApiTokenIssuer::class)
            ->issue($this->owner, $this->company, 'test', ['*'])
            ->plainTextToken;
    }

    protected function api()
    {
        return $this->withHeader('Authorization', "Bearer {$this->token}");
    }

    public function test_it_creates_a_field_with_a_boundary(): void
    {
        $response = $this->api()->postJson('/api/v1/fields', [
            'farm_id' => $this->farm->id,
            'name' => 'North Plot',
            'area_hectares' => 3.2,
            'boundary' => [
                ['lat' => 4.0511, 'lng' => 9.7679],
                ['lat' => 4.0520, 'lng' => 9.7690],
                ['lat' => 4.0500, 'lng' => 9.7700],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'North Plot')
            ->assertJsonCount(3, 'data.boundary');

        $field = Field::query()->where('farm_id', $this->farm->id)->sole();
        $this->assertCount(3, $field->boundary);
    }

    public function test_a_field_cannot_reference_a_farm_from_another_company(): void
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

        $this->api()->postJson('/api/v1/fields', ['farm_id' => $theirFarm->id, 'name' => 'Sneaky Plot'])
            ->assertStatus(422)
            ->assertJsonPath('error.details.farm_id.0', 'The selected farm id is invalid.');
    }

    public function test_it_filters_fields_by_farm(): void
    {
        app(CurrentCompany::class)->set($this->company);
        Field::create(['farm_id' => $this->farm->id, 'name' => 'A Plot']);
        $otherFarm = Farm::create(['name' => 'Other Farm']);
        Field::create(['farm_id' => $otherFarm->id, 'name' => 'B Plot']);

        $response = $this->api()->getJson("/api/v1/fields?farm_id={$this->farm->id}");

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name')->all();
        $this->assertSame(['A Plot'], $names);
    }

    public function test_it_updates_and_deletes_a_field(): void
    {
        app(CurrentCompany::class)->set($this->company);
        $field = Field::create(['farm_id' => $this->farm->id, 'name' => 'Old Plot']);

        $this->api()->patchJson("/api/v1/fields/{$field->id}", ['name' => 'New Plot'])
            ->assertOk()->assertJsonPath('data.name', 'New Plot');

        $this->api()->deleteJson("/api/v1/fields/{$field->id}")->assertNoContent();
        $this->assertSame(0, Field::query()->where('id', $field->id)->count());
    }
}
