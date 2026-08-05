<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Item;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\Stock\DeliveryReceiver;
use App\Services\Stock\StockLedger;
use App\Support\CurrentCompany;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The additive `batch_number`/`expires_on` columns on stock_movements, and
 * the optional params threaded through StockLedger::receive() and
 * DeliveryReceiver::receive() to set them. Recording only — nothing here
 * enforces FEFO consumption; see agri-platform-roadmap.md.
 */
class StockBatchExtensionTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected Company $company;

    protected Item $seedItem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->owner = User::factory()->create();
        $this->company = Company::create([
            'slug' => 'acme-'.Str::lower(Str::random(4)),
            'name' => 'Acme Sarl',
            'owner_id' => $this->owner->id,
            'currency' => 'XAF',
        ]);

        $this->joinCompany($this->company, $this->owner, Role::OWNER);
        app(CurrentCompany::class)->set($this->company);

        $this->seedItem = Item::create([
            'name' => 'Maize Seed 25kg',
            'type' => 'product',
            'track_stock' => true,
            'unit' => 'bag',
        ]);
    }

    public function test_stock_ledger_receive_records_batch_and_expiry(): void
    {
        $movement = app(StockLedger::class)->receive(
            company: $this->company,
            item: $this->seedItem,
            quantity: 10,
            unitCost: 8000,
            batchNumber: 'SEED-2026-A',
            expiresOn: '2027-01-01',
        );

        $this->assertSame('SEED-2026-A', $movement->batch_number);
        $this->assertSame('2027-01-01', $movement->expires_on->toDateString());
    }

    public function test_stock_ledger_receive_defaults_batch_and_expiry_to_null(): void
    {
        $movement = app(StockLedger::class)->receive(
            company: $this->company,
            item: $this->seedItem,
            quantity: 10,
        );

        $this->assertNull($movement->batch_number);
        $this->assertNull($movement->expires_on);
    }

    public function test_stock_ledger_receive_records_a_polymorphic_reference(): void
    {
        $movement = app(StockLedger::class)->receive(
            company: $this->company,
            item: $this->seedItem,
            quantity: 10,
            referenceType: 'App\\Models\\PurchaseOrder',
            referenceId: 'po-123',
        );

        $this->assertSame('App\\Models\\PurchaseOrder', $movement->reference_type);
        $this->assertSame('po-123', $movement->reference_id);
    }

    public function test_delivery_receiver_passes_per_line_batch_and_expiry_through(): void
    {
        app(DeliveryReceiver::class)->receive(
            company: $this->company,
            lines: [
                [
                    'item_id' => $this->seedItem->id,
                    'quantity' => 5,
                    'unit_cost' => 8000,
                    'batch_number' => 'SEED-2026-B',
                    'expires_on' => '2027-06-01',
                ],
            ],
            actor: $this->owner,
        );

        $movement = StockMovement::query()->where('item_id', $this->seedItem->id)->sole();

        $this->assertSame('SEED-2026-B', $movement->batch_number);
        $this->assertSame('2027-06-01', $movement->expires_on->toDateString());
    }

    public function test_delivery_receiver_passes_reference_options_through(): void
    {
        app(DeliveryReceiver::class)->receive(
            company: $this->company,
            lines: [['item_id' => $this->seedItem->id, 'quantity' => 5, 'unit_cost' => 8000]],
            options: ['reference_type' => 'App\\Models\\PurchaseOrder', 'reference_id' => 'po-456'],
            actor: $this->owner,
        );

        $movement = StockMovement::query()->where('item_id', $this->seedItem->id)->sole();

        $this->assertSame('App\\Models\\PurchaseOrder', $movement->reference_type);
        $this->assertSame('po-456', $movement->reference_id);
    }

    public function test_existing_sales_movements_are_unaffected_by_the_new_columns(): void
    {
        app(StockLedger::class)->receive(company: $this->company, item: $this->seedItem, quantity: 10);

        $sale = StockMovement::create([
            'company_id' => $this->company->id,
            'item_id' => $this->seedItem->id,
            'quantity' => -3,
            'reason' => 'sale',
            'occurred_at' => now(),
        ]);

        $this->assertNull($sale->batch_number);
        $this->assertNull($sale->expires_on);
        $this->assertSame(7.0, (float) $this->seedItem->fresh()->stockOnHand());
    }
}
