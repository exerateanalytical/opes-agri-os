<?php

namespace Tests\Feature;

use App\Livewire\Stock\Trace as StockTrace;
use App\Models\Company;
use App\Models\Item;
use App\Models\Role;
use App\Models\User;
use App\Services\Stock\StockLedger;
use App\Support\CurrentCompany;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class BatchTraceTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected Company $company;

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

        $this->maize = Item::create(['name' => 'Maize', 'type' => 'product', 'track_stock' => true, 'unit' => 'bag']);

        app(StockLedger::class)->receive(
            company: $this->company,
            item: $this->maize,
            quantity: 50,
            unitCost: 5,
            batchNumber: 'MAIZE-2026-A',
        );
    }

    public function test_it_shows_a_batchs_movements(): void
    {
        Livewire::actingAs($this->owner)
            ->test(StockTrace::class)
            ->set('itemId', $this->maize->id)
            ->set('batchNumber', 'MAIZE-2026-A')
            ->assertViewHas('movements', fn ($movements) => $movements->count() === 1);
    }
}
