<?php

namespace Tests\Feature\Api\V1;

use App\Models\Animal;
use App\Models\AnimalBatch;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Services\ApiTokenIssuer;
use App\Support\CurrentCompany;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AnimalBatchesApiTest extends TestCase
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

    public function test_it_creates_a_batch_with_current_count_matching_initial(): void
    {
        $response = $this->api()->postJson('/api/v1/animal-batches', [
            'species' => 'Broiler',
            'initial_count' => 200,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.initial_count', 200)
            ->assertJsonPath('data.current_count', 200)
            ->assertJsonPath('data.status', 'active');
    }

    public function test_adjusting_count_down_reduces_current_count(): void
    {
        app(CurrentCompany::class)->set($this->company);
        $batch = AnimalBatch::create(['species' => 'Broiler', 'initial_count' => 200, 'current_count' => 200, 'status' => 'active']);

        $response = $this->api()->postJson("/api/v1/animal-batches/{$batch->id}/adjust-count", [
            'change' => -15,
            'reason' => 'mortality',
        ]);

        $response->assertOk()->assertJsonPath('data.current_count', 185);
    }

    public function test_a_count_cannot_go_below_zero(): void
    {
        app(CurrentCompany::class)->set($this->company);
        $batch = AnimalBatch::create(['species' => 'Broiler', 'initial_count' => 5, 'current_count' => 5, 'status' => 'active']);

        $this->api()->postJson("/api/v1/animal-batches/{$batch->id}/adjust-count", [
            'change' => -10,
        ])->assertStatus(409);
    }

    public function test_an_animal_records_its_sire_and_dam(): void
    {
        app(CurrentCompany::class)->set($this->company);
        $sire = Animal::create(['species' => 'Cattle', 'sex' => 'male', 'status' => 'active']);
        $dam = Animal::create(['species' => 'Cattle', 'sex' => 'female', 'status' => 'active']);

        $response = $this->api()->postJson('/api/v1/animals', [
            'species' => 'Cattle',
            'sire_id' => $sire->id,
            'dam_id' => $dam->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.sire_id', $sire->id)
            ->assertJsonPath('data.dam_id', $dam->id);
    }

    public function test_a_female_cannot_be_set_as_sire(): void
    {
        app(CurrentCompany::class)->set($this->company);
        $notMale = Animal::create(['species' => 'Cattle', 'sex' => 'female', 'status' => 'active']);

        $this->api()->postJson('/api/v1/animals', [
            'species' => 'Cattle',
            'sire_id' => $notMale->id,
        ])->assertStatus(422);
    }

    public function test_batches_are_scoped_to_their_company(): void
    {
        $otherOwner = User::factory()->create();
        $otherCompany = Company::create([
            'slug' => 'other-'.Str::lower(Str::random(4)),
            'name' => 'Other Ltd',
            'owner_id' => $otherOwner->id,
            'currency' => 'USD',
        ]);
        app(CurrentCompany::class)->set($otherCompany);
        $theirBatch = AnimalBatch::create(['species' => 'Broiler', 'initial_count' => 10, 'current_count' => 10, 'status' => 'active']);

        $this->api()->getJson("/api/v1/animal-batches/{$theirBatch->id}")->assertStatus(404);
    }
}
