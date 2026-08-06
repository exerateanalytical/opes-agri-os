<?php

namespace Tests\Feature;

use App\Livewire\Livestock\Index as LivestockIndex;
use App\Livewire\Livestock\RecordHealth;
use App\Livewire\Livestock\RecordProduction;
use App\Models\Animal;
use App\Models\AnimalBatch;
use App\Models\Company;
use App\Models\Item;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\Livestock\BatchCountAdjuster;
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

    public function test_an_animal_records_its_sire_and_dam(): void
    {
        $sire = Animal::create(['species' => 'Cattle', 'sex' => 'male', 'status' => 'active']);
        $dam = Animal::create(['species' => 'Cattle', 'sex' => 'female', 'status' => 'active']);

        Livewire::actingAs($this->owner)
            ->test(LivestockIndex::class)
            ->call('startAdding')
            ->set('species', 'Cattle')
            ->set('sireId', $sire->id)
            ->set('damId', $dam->id)
            ->call('save')
            ->assertHasNoErrors();

        $calf = Animal::query()->where('company_id', $this->company->id)->where('species', 'Cattle')
            ->whereNotIn('id', [$sire->id, $dam->id])->sole();
        $this->assertSame($sire->id, $calf->sire_id);
        $this->assertSame($dam->id, $calf->dam_id);
    }

    public function test_it_creates_a_batch_and_adjusts_its_count(): void
    {
        $component = Livewire::actingAs($this->owner)
            ->test(LivestockIndex::class)
            ->call('startAddingBatch')
            ->set('batchSpecies', 'Broiler')
            ->set('batchInitialCount', '200')
            ->call('saveBatch')
            ->assertHasNoErrors();

        $batch = AnimalBatch::query()->where('company_id', $this->company->id)->sole();
        $this->assertSame(200, $batch->current_count);

        $component->call('adjustBatchCount', $batch->id, -20);
        $this->assertSame(180, $batch->fresh()->current_count);
    }

    public function test_a_batch_count_cannot_go_below_zero(): void
    {
        $batch = AnimalBatch::create(['species' => 'Broiler', 'initial_count' => 5, 'current_count' => 5, 'status' => 'active']);

        Livewire::actingAs($this->owner)
            ->test(LivestockIndex::class)
            ->call('adjustBatchCount', $batch->id, -10)
            ->assertHasErrors('batch');

        $this->assertSame(5, $batch->fresh()->current_count);
    }

    public function test_the_adjuster_re_reads_the_batch_under_lock_so_the_audit_trail_stays_consistent(): void
    {
        $batch = AnimalBatch::create(['species' => 'Broiler', 'initial_count' => 100, 'current_count' => 100, 'status' => 'active']);

        // Simulate a caller holding a stale in-memory copy of the batch (as a
        // concurrent request would) by adjusting through a *different*
        // instance than the one we inspect afterwards. If the service ever
        // regresses to computing $newCount from the caller's copy instead of
        // a locked re-fetch, this would still "work" in SQLite's single
        // connection, but the resulting_count/current_count relationship
        // below is what the audit trail actually depends on and must always
        // hold regardless of which instance triggered the write.
        $staleHandle = AnimalBatch::query()->findOrFail($batch->id);

        app(BatchCountAdjuster::class)->adjust($staleHandle, -30);

        $batch->refresh();
        $adjustment = $batch->adjustments()->latest('id')->first();

        $this->assertSame(70, $batch->current_count);
        $this->assertSame($batch->current_count, $adjustment->resulting_count);
        $this->assertSame((int) $batch->adjustments()->sum('change'), $batch->current_count - $batch->initial_count);
    }

    public function test_sequential_adjustments_keep_the_audit_trail_consistent_with_the_running_count(): void
    {
        $batch = AnimalBatch::create(['species' => 'Broiler', 'initial_count' => 100, 'current_count' => 100, 'status' => 'active']);
        $adjuster = app(BatchCountAdjuster::class);

        $adjuster->adjust($batch, -10);
        $adjuster->adjust($batch, -5);
        $adjuster->adjust($batch, 3);

        $batch->refresh();

        $this->assertSame(88, $batch->current_count);
        $this->assertSame((int) $batch->adjustments()->sum('change'), $batch->current_count - $batch->initial_count);

        // Each adjustment's resulting_count must equal the running total up
        // to and including that adjustment — the audit trail must always
        // reconstruct to the batch's current state.
        $running = $batch->initial_count;
        foreach ($batch->adjustments()->orderBy('id')->get() as $adjustment) {
            $running += $adjustment->change;
            $this->assertSame($running, $adjustment->resulting_count);
        }

        $this->assertSame($batch->current_count, $batch->adjustments()->latest('id')->first()->resulting_count);
    }
}
