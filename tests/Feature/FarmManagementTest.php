<?php

namespace Tests\Feature;

use App\Livewire\Farms\Index as FarmsIndex;
use App\Models\Company;
use App\Models\Farm;
use App\Models\Field;
use App\Models\Role;
use App\Models\Season;
use App\Models\User;
use App\Support\CurrentCompany;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class FarmManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected Company $company;

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
    }

    public function test_the_page_requires_farms_view(): void
    {
        $cashier = User::factory()->create();
        $this->joinCompany($this->company, $cashier, Role::CASHIER);

        Livewire::actingAs($cashier)->test(FarmsIndex::class)->assertForbidden();
    }

    public function test_it_creates_a_farm(): void
    {
        Livewire::actingAs($this->owner)
            ->test(FarmsIndex::class)
            ->call('startAddingFarm')
            ->set('farmName', 'Green Valley Farm')
            ->set('farmSize', '12.5')
            ->call('saveFarm')
            ->assertHasNoErrors();

        $this->assertSame('Green Valley Farm', Farm::query()->where('company_id', $this->company->id)->sole()->name);
    }

    public function test_it_creates_a_field_with_boundary_points(): void
    {
        $farm = Farm::create(['name' => 'Green Valley Farm']);

        Livewire::actingAs($this->owner)
            ->test(FarmsIndex::class)
            ->call('startAddingField')
            ->set('fieldFarmId', $farm->id)
            ->set('fieldName', 'North Plot')
            ->call('addBoundaryPoint')
            ->set('fieldBoundary.0.lat', '4.05')
            ->set('fieldBoundary.0.lng', '9.76')
            ->call('saveField')
            ->assertHasNoErrors();

        $field = Field::query()->where('farm_id', $farm->id)->sole();
        $this->assertCount(1, $field->boundary);
        $this->assertSame(4.05, $field->boundary[0]['lat']);
    }

    public function test_it_creates_a_season(): void
    {
        Livewire::actingAs($this->owner)
            ->test(FarmsIndex::class)
            ->call('startAddingSeason')
            ->set('seasonName', '2026 Long Rains')
            ->set('seasonStartsOn', '2026-03-01')
            ->call('saveSeason')
            ->assertHasNoErrors();

        $this->assertSame('2026 Long Rains', Season::query()->where('company_id', $this->company->id)->sole()->name);
    }

    public function test_farms_belong_to_their_company_alone(): void
    {
        $otherOwner = User::factory()->create();
        $otherCompany = Company::create([
            'slug' => 'other-'.Str::lower(Str::random(4)),
            'name' => 'Other Ltd',
            'owner_id' => $otherOwner->id,
            'currency' => 'USD',
        ]);
        app(CurrentCompany::class)->set($otherCompany);
        Farm::create(['name' => 'Theirs']);

        app(CurrentCompany::class)->set($this->company);
        Farm::create(['name' => 'Ours']);

        Livewire::actingAs($this->owner)
            ->test(FarmsIndex::class)
            ->assertViewHas('farms', fn ($farms) => $farms->pluck('name')->all() === ['Ours']);
    }

    public function test_a_disabled_module_hides_the_page(): void
    {
        $this->company->forceFill(['modules' => ['farms' => false]])->save();

        Livewire::actingAs($this->owner)->test(FarmsIndex::class)->assertForbidden();
    }

    public function test_it_records_a_soil_test(): void
    {
        $farm = Farm::create(['name' => 'Green Valley Farm']);
        $field = Field::create(['farm_id' => $farm->id, 'name' => 'North Plot']);

        Livewire::actingAs($this->owner)
            ->test(FarmsIndex::class)
            ->call('openSoilTest', $field->id)
            ->set('soilTestedOn', '2026-08-01')
            ->set('soilPh', '6.5')
            ->call('saveSoilTest')
            ->assertHasNoErrors();

        $this->assertSame(1, $field->soilTestRecords()->count());
    }

    public function test_it_records_an_irrigation_log(): void
    {
        $farm = Farm::create(['name' => 'Green Valley Farm']);
        $field = Field::create(['farm_id' => $farm->id, 'name' => 'North Plot']);

        Livewire::actingAs($this->owner)
            ->test(FarmsIndex::class)
            ->call('openIrrigation', $field->id)
            ->set('irrigatedOn', '2026-08-01')
            ->set('irrigationMethod', 'drip')
            ->set('irrigationDuration', '30')
            ->call('saveIrrigation')
            ->assertHasNoErrors();

        $this->assertSame(1, $field->irrigationLogs()->count());
    }
}
