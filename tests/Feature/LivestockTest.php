<?php

namespace Tests\Feature;

use App\Livewire\Livestock\Index as LivestockIndex;
use App\Livewire\Livestock\RecordHealth;
use App\Livewire\Livestock\RecordProduction;
use App\Models\Animal;
use App\Models\Company;
use App\Models\Item;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\User;
use App\Support\CurrentCompany;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class LivestockTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected Company $company;

    protected Item $milk;

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

        $this->milk = Item::create(['name' => 'Milk', 'type' => 'product', 'track_stock' => true, 'unit' => 'litre']);
    }

    public function test_it_creates_an_animal(): void
    {
        Livewire::actingAs($this->owner)
            ->test(LivestockIndex::class)
            ->call('startAdding')
            ->set('species', 'Cattle')
            ->set('tagNumber', 'COW-001')
            ->call('save')
            ->assertHasNoErrors();

        $animal = Animal::query()->where('company_id', $this->company->id)->sole();
        $this->assertSame('active', $animal->status);
    }

    public function test_recording_a_health_event(): void
    {
        $animal = Animal::create(['species' => 'Cattle', 'status' => 'active']);

        Livewire::actingAs($this->owner)
            ->test(RecordHealth::class)
            ->call('open', $animal->id)
            ->set('description', 'FMD vaccine')
            ->set('administeredOn', '2026-08-01')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(1, $animal->healthRecords()->count());
    }

    public function test_recording_production_writes_stock(): void
    {
        $animal = Animal::create(['species' => 'Cattle', 'status' => 'active']);

        Livewire::actingAs($this->owner)
            ->test(RecordProduction::class)
            ->call('open', $animal->id)
            ->set('itemId', $this->milk->id)
            ->set('quantity', '10')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(1, StockMovement::query()->where('item_id', $this->milk->id)->count());
        $this->assertSame(10.0, (float) $this->milk->fresh()->stockOnHand());
    }

    public function test_a_disabled_livestock_module_hides_the_page(): void
    {
        $this->company->forceFill(['modules' => ['livestock' => false]])->save();

        Livewire::actingAs($this->owner)->test(LivestockIndex::class)->assertForbidden();
    }
}
