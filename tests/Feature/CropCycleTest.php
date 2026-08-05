<?php

namespace Tests\Feature;

use App\Livewire\Crops\Index as CropsIndex;
use App\Livewire\Crops\RecordHarvest;
use App\Models\Company;
use App\Models\CropCycle;
use App\Models\Farm;
use App\Models\Field;
use App\Models\Item;
use App\Models\Role;
use App\Models\Season;
use App\Models\StockMovement;
use App\Models\User;
use App\Support\CurrentCompany;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class CropCycleTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected Company $company;

    protected Field $field;

    protected Season $season;

    protected Item $maize;

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
    }

    public function test_it_creates_a_crop_cycle(): void
    {
        Livewire::actingAs($this->owner)
            ->test(CropsIndex::class)
            ->call('startAdding')
            ->set('fieldId', $this->field->id)
            ->set('seasonId', $this->season->id)
            ->set('itemId', $this->maize->id)
            ->call('save')
            ->assertHasNoErrors();

        $cycle = CropCycle::query()->where('company_id', $this->company->id)->sole();
        $this->assertSame('planned', $cycle->status);
    }

    public function test_it_walks_the_status_lifecycle(): void
    {
        $cycle = CropCycle::create([
            'field_id' => $this->field->id,
            'season_id' => $this->season->id,
            'item_id' => $this->maize->id,
        ]);

        $component = Livewire::actingAs($this->owner)->test(CropsIndex::class);

        $component->call('markPlanted', $cycle->id);
        $this->assertSame('planted', $cycle->fresh()->status);

        $component->call('markGrowing', $cycle->id);
        $this->assertSame('growing', $cycle->fresh()->status);
    }

    public function test_recording_a_harvest_writes_stock_and_updates_the_cycle(): void
    {
        $cycle = CropCycle::create([
            'field_id' => $this->field->id,
            'season_id' => $this->season->id,
            'item_id' => $this->maize->id,
            'status' => 'growing',
        ]);

        Livewire::actingAs($this->owner)
            ->test(RecordHarvest::class)
            ->call('open', $cycle->id)
            ->set('quantity', '30')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('harvested', $cycle->fresh()->status);
        $this->assertSame(1, StockMovement::query()->where('item_id', $this->maize->id)->count());
    }

    public function test_a_double_harvest_shows_an_error_instead_of_throwing(): void
    {
        $cycle = CropCycle::create([
            'field_id' => $this->field->id,
            'season_id' => $this->season->id,
            'item_id' => $this->maize->id,
            'status' => 'harvested',
            'actual_yield_qty' => 10,
        ]);

        Livewire::actingAs($this->owner)
            ->test(RecordHarvest::class)
            ->call('open', $cycle->id)
            ->set('quantity', '5')
            ->call('save')
            ->assertHasErrors('quantity');
    }

    public function test_a_disabled_crops_module_hides_the_page(): void
    {
        $this->company->forceFill(['modules' => ['crops' => false]])->save();

        Livewire::actingAs($this->owner)->test(CropsIndex::class)->assertForbidden();
    }

    public function test_disabling_farms_also_disables_crops(): void
    {
        $this->company->forceFill(['modules' => ['farms' => false]])->save();

        Livewire::actingAs($this->owner)->test(CropsIndex::class)->assertForbidden();
    }
}
