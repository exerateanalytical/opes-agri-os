<?php

namespace Tests\Feature\Api\V1;

use App\Models\Company;
use App\Models\CropCycle;
use App\Models\Farm;
use App\Models\Field;
use App\Models\Item;
use App\Models\Role;
use App\Models\Season;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\ApiTokenIssuer;
use App\Support\CurrentCompany;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Tests\TestCase;

class CropCyclesApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected Company $company;

    protected Field $field;

    protected Season $season;

    protected Item $maize;

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

        $farm = Farm::create(['name' => 'Green Valley Farm']);
        $this->field = Field::create(['farm_id' => $farm->id, 'name' => 'North Plot']);
        $this->season = Season::create(['name' => '2026 Long Rains', 'starts_on' => '2026-03-01']);
        $this->maize = Item::create(['name' => 'Maize', 'type' => 'product', 'track_stock' => true, 'unit' => 'bag']);

        $this->token = app(ApiTokenIssuer::class)
            ->issue($this->owner, $this->company, 'test', ['*'])
            ->plainTextToken;
    }

    protected function api()
    {
        return $this->withHeader('Authorization', "Bearer {$this->token}");
    }

    public function test_it_creates_a_crop_cycle_in_planned_status(): void
    {
        $response = $this->api()->postJson('/api/v1/crop-cycles', [
            'field_id' => $this->field->id,
            'season_id' => $this->season->id,
            'item_id' => $this->maize->id,
            'planned_planting_date' => '2026-03-05',
            'planned_yield_qty' => 100,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'planned')
            ->assertJsonPath('data.planned_yield_qty', '100.000');
    }

    public function test_a_crop_cycle_cannot_reference_a_field_from_another_company(): void
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
        $theirField = Field::create(['farm_id' => $theirFarm->id, 'name' => 'Theirs']);

        $this->api()->postJson('/api/v1/crop-cycles', [
            'field_id' => $theirField->id,
            'season_id' => $this->season->id,
        ])->assertStatus(422)->assertJsonPath('error.details.field_id.0', 'The selected field id is invalid.');
    }

    public function test_it_updates_status_and_growth_stage(): void
    {
        app(CurrentCompany::class)->set($this->company);
        $cycle = CropCycle::create(['field_id' => $this->field->id, 'season_id' => $this->season->id]);

        $this->api()->patchJson("/api/v1/crop-cycles/{$cycle->id}", [
            'status' => 'growing',
            'growth_stage' => 'flowering',
        ])->assertOk()->assertJsonPath('data.status', 'growing')->assertJsonPath('data.growth_stage', 'flowering');
    }

    public function test_the_update_endpoint_cannot_set_status_to_harvested(): void
    {
        app(CurrentCompany::class)->set($this->company);
        $cycle = CropCycle::create(['field_id' => $this->field->id, 'season_id' => $this->season->id]);

        $this->api()->patchJson("/api/v1/crop-cycles/{$cycle->id}", ['status' => 'harvested'])
            ->assertStatus(422);
    }

    public function test_it_records_a_harvest_and_writes_a_stock_movement(): void
    {
        app(CurrentCompany::class)->set($this->company);
        $cycle = CropCycle::create([
            'field_id' => $this->field->id,
            'season_id' => $this->season->id,
            'item_id' => $this->maize->id,
            'status' => 'growing',
        ]);

        $response = $this->api()->postJson("/api/v1/crop-cycles/{$cycle->id}/harvest", [
            'quantity' => 42,
            'unit_cost' => 5,
            'batch_number' => 'MAIZE-2026-A',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'harvested')
            ->assertJsonPath('data.actual_yield_qty', '42.000')
            ->assertJsonPath('data.yield_unit', 'bag');

        $movement = StockMovement::query()->where('item_id', $this->maize->id)->sole();
        $this->assertSame('42.000', $movement->quantity);
        $this->assertSame('harvest', $movement->reason);
        $this->assertSame('MAIZE-2026-A', $movement->batch_number);
        $this->assertSame(CropCycle::class, $movement->reference_type);
        $this->assertSame($cycle->id, $movement->reference_id);
        $this->assertSame(42.0, (float) $this->maize->fresh()->stockOnHand());
    }

    public function test_a_harvested_cycle_cannot_be_reopened_through_the_update_endpoint(): void
    {
        app(CurrentCompany::class)->set($this->company);
        $cycle = CropCycle::create([
            'field_id' => $this->field->id,
            'season_id' => $this->season->id,
            'item_id' => $this->maize->id,
            'status' => 'growing',
        ]);

        $this->api()->postJson("/api/v1/crop-cycles/{$cycle->id}/harvest", ['quantity' => 42])->assertOk();

        $this->api()->patchJson("/api/v1/crop-cycles/{$cycle->id}", ['status' => 'growing'])
            ->assertStatus(409);

        $this->assertSame('harvested', $cycle->fresh()->status);

        // The harvest endpoint's own guard should still refuse a second
        // harvest now that the reopen path via update() is closed too.
        $this->api()->postJson("/api/v1/crop-cycles/{$cycle->id}/harvest", ['quantity' => 10])
            ->assertStatus(409);

        $movementsCount = StockMovement::query()->where('item_id', $this->maize->id)->count();
        $this->assertSame(1, $movementsCount);
    }

    public function test_a_closed_cycle_cannot_have_its_growth_stage_changed(): void
    {
        app(CurrentCompany::class)->set($this->company);
        $cycle = CropCycle::create([
            'field_id' => $this->field->id,
            'season_id' => $this->season->id,
            'item_id' => $this->maize->id,
            'status' => 'closed',
        ]);

        $this->api()->patchJson("/api/v1/crop-cycles/{$cycle->id}", ['growth_stage' => 'flowering'])
            ->assertStatus(409);
    }

    public function test_notes_can_still_be_edited_on_a_harvested_cycle(): void
    {
        app(CurrentCompany::class)->set($this->company);
        $cycle = CropCycle::create([
            'field_id' => $this->field->id,
            'season_id' => $this->season->id,
            'item_id' => $this->maize->id,
            'status' => 'harvested',
        ]);

        $this->api()->patchJson("/api/v1/crop-cycles/{$cycle->id}", ['notes' => 'Good yield'])
            ->assertOk()->assertJsonPath('data.notes', 'Good yield');
    }

    public function test_harvesting_twice_is_a_conflict(): void
    {
        app(CurrentCompany::class)->set($this->company);
        $cycle = CropCycle::create([
            'field_id' => $this->field->id,
            'season_id' => $this->season->id,
            'item_id' => $this->maize->id,
        ]);

        $this->api()->postJson("/api/v1/crop-cycles/{$cycle->id}/harvest", ['quantity' => 10])->assertOk();

        $this->api()->postJson("/api/v1/crop-cycles/{$cycle->id}/harvest", ['quantity' => 10])
            ->assertStatus(409);
    }

    public function test_a_cycle_with_no_item_cannot_be_harvested(): void
    {
        app(CurrentCompany::class)->set($this->company);
        $cycle = CropCycle::create(['field_id' => $this->field->id, 'season_id' => $this->season->id]);

        $this->api()->postJson("/api/v1/crop-cycles/{$cycle->id}/harvest", ['quantity' => 10])
            ->assertStatus(409);
    }

    public function test_a_token_without_the_record_harvest_ability_is_refused(): void
    {
        app(CurrentCompany::class)->set($this->company);
        $cycle = CropCycle::create([
            'field_id' => $this->field->id,
            'season_id' => $this->season->id,
            'item_id' => $this->maize->id,
        ]);

        $viewOnlyToken = app(ApiTokenIssuer::class)
            ->issue($this->owner, $this->company, 'view-only', ['crops.view'])
            ->plainTextToken;

        Auth::forgetGuards();

        $this->withHeader('Authorization', "Bearer {$viewOnlyToken}")
            ->postJson("/api/v1/crop-cycles/{$cycle->id}/harvest", ['quantity' => 10])
            ->assertStatus(403);
    }

    public function test_crop_cycles_are_scoped_to_their_company(): void
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
        $theirField = Field::create(['farm_id' => $theirFarm->id, 'name' => 'Theirs']);
        $theirSeason = Season::create(['name' => 'Theirs', 'starts_on' => '2026-01-01']);
        $theirCycle = CropCycle::create(['field_id' => $theirField->id, 'season_id' => $theirSeason->id]);

        $this->api()->getJson("/api/v1/crop-cycles/{$theirCycle->id}")->assertStatus(404);
    }
}
