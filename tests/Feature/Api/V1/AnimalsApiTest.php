<?php

namespace Tests\Feature\Api\V1;

use App\Models\Animal;
use App\Models\Company;
use App\Models\Farm;
use App\Models\Item;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\ApiTokenIssuer;
use App\Support\CurrentCompany;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Tests\TestCase;

class AnimalsApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected Company $company;

    protected Farm $farm;

    protected Item $milk;

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
        $this->milk = Item::create(['name' => 'Milk', 'type' => 'product', 'track_stock' => true, 'unit' => 'litre']);

        $this->token = app(ApiTokenIssuer::class)
            ->issue($this->owner, $this->company, 'test', ['*'])
            ->plainTextToken;
    }

    protected function api()
    {
        return $this->withHeader('Authorization', "Bearer {$this->token}");
    }

    public function test_it_creates_an_animal_in_active_status(): void
    {
        $response = $this->api()->postJson('/api/v1/animals', [
            'farm_id' => $this->farm->id,
            'species' => 'Cattle',
            'breed' => 'Holstein',
            'tag_number' => 'COW-001',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.species', 'Cattle');
    }

    public function test_an_animal_cannot_reference_a_farm_from_another_company(): void
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

        $this->api()->postJson('/api/v1/animals', [
            'farm_id' => $theirFarm->id,
            'species' => 'Cattle',
        ])->assertStatus(422)->assertJsonPath('error.details.farm_id.0', 'The selected farm id is invalid.');
    }

    public function test_it_records_a_health_event(): void
    {
        app(CurrentCompany::class)->set($this->company);
        $animal = Animal::create(['species' => 'Cattle', 'status' => 'active']);

        $response = $this->api()->postJson("/api/v1/animals/{$animal->id}/health-records", [
            'record_type' => 'vaccination',
            'description' => 'FMD vaccine',
            'administered_on' => '2026-08-01',
            'next_due_on' => '2027-08-01',
        ]);

        $response->assertCreated()->assertJsonPath('data.record_type', 'vaccination');
        $this->assertSame(1, $animal->healthRecords()->count());
    }

    public function test_it_records_production_and_writes_a_stock_movement(): void
    {
        app(CurrentCompany::class)->set($this->company);
        $animal = Animal::create(['species' => 'Cattle', 'status' => 'active']);

        $response = $this->api()->postJson("/api/v1/animals/{$animal->id}/production-records", [
            'item_id' => $this->milk->id,
            'quantity' => 12,
            'unit_cost' => 2,
        ]);

        $response->assertCreated()->assertJsonPath('data.quantity', '12.000');

        $movement = StockMovement::query()->where('item_id', $this->milk->id)->sole();
        $this->assertSame('12.000', $movement->quantity);
        $this->assertSame('livestock-production', $movement->reason);
        $this->assertSame(12.0, (float) $this->milk->fresh()->stockOnHand());
    }

    public function test_production_without_an_item_is_recorded_but_writes_no_stock(): void
    {
        app(CurrentCompany::class)->set($this->company);
        $animal = Animal::create(['species' => 'Cattle', 'status' => 'active']);

        $this->api()->postJson("/api/v1/animals/{$animal->id}/production-records", [
            'quantity' => 5,
        ])->assertCreated();

        $this->assertSame(0, StockMovement::query()->count());
        $this->assertSame(1, $animal->productionRecords()->count());
    }

    public function test_a_token_without_the_record_health_ability_is_refused(): void
    {
        app(CurrentCompany::class)->set($this->company);
        $animal = Animal::create(['species' => 'Cattle', 'status' => 'active']);

        $viewOnlyToken = app(ApiTokenIssuer::class)
            ->issue($this->owner, $this->company, 'view-only', ['livestock.view'])
            ->plainTextToken;

        Auth::forgetGuards();

        $this->withHeader('Authorization', "Bearer {$viewOnlyToken}")
            ->postJson("/api/v1/animals/{$animal->id}/health-records", [
                'record_type' => 'checkup',
                'description' => 'Routine',
                'administered_on' => '2026-08-01',
            ])->assertStatus(403);
    }

    public function test_animals_are_scoped_to_their_company(): void
    {
        $otherOwner = User::factory()->create();
        $otherCompany = Company::create([
            'slug' => 'other-'.Str::lower(Str::random(4)),
            'name' => 'Other Ltd',
            'owner_id' => $otherOwner->id,
            'currency' => 'USD',
        ]);
        app(CurrentCompany::class)->set($otherCompany);
        $theirAnimal = Animal::create(['species' => 'Goat', 'status' => 'active']);

        $this->api()->getJson("/api/v1/animals/{$theirAnimal->id}")->assertStatus(404);
    }
}
