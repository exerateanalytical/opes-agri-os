<?php

namespace Tests\Feature;

use App\Livewire\Procurement\Index as ProcurementIndex;
use App\Livewire\Procurement\ReceivePurchaseOrder;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Item;
use App\Models\PurchaseOrder;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\User;
use App\Support\CurrentCompany;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class PurchaseOrderTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected Company $company;

    protected Contact $supplier;

    protected Item $fertiliser;

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

        $this->supplier = Contact::create(['name' => 'AgriSupply Co', 'type' => 'supplier']);
        $this->fertiliser = Item::create(['name' => 'Fertiliser', 'type' => 'product', 'track_stock' => true, 'unit' => 'bag']);
    }

    public function test_it_creates_a_draft_purchase_order(): void
    {
        Livewire::actingAs($this->owner)
            ->test(ProcurementIndex::class)
            ->call('startAdding')
            ->set('supplierId', $this->supplier->id)
            ->set('lines.0.item_id', $this->fertiliser->id)
            ->set('lines.0.quantity', '10')
            ->set('lines.0.unit_cost', '5000')
            ->call('save')
            ->assertHasNoErrors();

        $po = PurchaseOrder::query()->where('company_id', $this->company->id)->sole();
        $this->assertSame('draft', $po->status);
        $this->assertSame('50000.00', $po->total);
    }

    public function test_issuing_assigns_a_number(): void
    {
        $po = PurchaseOrder::create([
            'supplier_id' => $this->supplier->id, 'status' => 'draft', 'order_date' => now(),
        ]);
        $po->lines()->create(['item_id' => $this->fertiliser->id, 'quantity' => 10, 'unit_cost' => 5000]);

        Livewire::actingAs($this->owner)
            ->test(ProcurementIndex::class)
            ->call('issue', $po->id)
            ->assertHasNoErrors();

        $po->refresh();
        $this->assertSame('issued', $po->status);
        $this->assertNotNull($po->number);
    }

    public function test_receiving_writes_stock_and_updates_the_order(): void
    {
        $po = PurchaseOrder::create([
            'supplier_id' => $this->supplier->id, 'status' => 'issued', 'number' => 'PO-2026-00001', 'order_date' => now(),
        ]);
        $line = $po->lines()->create(['item_id' => $this->fertiliser->id, 'quantity' => 10, 'unit_cost' => 5000]);

        Livewire::actingAs($this->owner)
            ->test(ReceivePurchaseOrder::class)
            ->call('open', $po->id)
            ->set('lines.0.quantity', '10')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('received', $po->fresh()->status);
        $this->assertSame(10.0, (float) $this->fertiliser->fresh()->stockOnHand());
        $movement = StockMovement::query()->where('item_id', $this->fertiliser->id)->sole();
        $this->assertSame(PurchaseOrder::class, $movement->reference_type);
        $this->assertSame($po->id, $movement->reference_id);
    }

    public function test_a_disabled_procurement_module_hides_the_page(): void
    {
        $this->company->forceFill(['modules' => ['procurement' => false]])->save();

        Livewire::actingAs($this->owner)->test(ProcurementIndex::class)->assertForbidden();
    }
}
