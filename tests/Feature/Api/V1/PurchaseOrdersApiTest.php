<?php

namespace Tests\Feature\Api\V1;

use App\Models\Company;
use App\Models\Contact;
use App\Models\Item;
use App\Models\PurchaseOrder;
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

class PurchaseOrdersApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected Company $company;

    protected Contact $supplier;

    protected Item $fertiliser;

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

        $this->supplier = Contact::create(['name' => 'AgriSupply Co', 'type' => 'supplier']);
        $this->fertiliser = Item::create(['name' => 'Fertiliser', 'type' => 'product', 'track_stock' => true, 'unit' => 'bag']);

        $this->token = app(ApiTokenIssuer::class)
            ->issue($this->owner, $this->company, 'test', ['*'])
            ->plainTextToken;
    }

    protected function api()
    {
        return $this->withHeader('Authorization', "Bearer {$this->token}");
    }

    protected function createPurchaseOrder(): PurchaseOrder
    {
        $response = $this->api()->postJson('/api/v1/purchase-orders', [
            'supplier_id' => $this->supplier->id,
            'order_date' => '2026-08-01',
            'lines' => [
                ['item_id' => $this->fertiliser->id, 'quantity' => 10, 'unit_cost' => 5000],
            ],
        ]);

        return PurchaseOrder::findOrFail($response->json('data.id'));
    }

    public function test_it_creates_a_draft_purchase_order_with_lines(): void
    {
        $response = $this->api()->postJson('/api/v1/purchase-orders', [
            'supplier_id' => $this->supplier->id,
            'order_date' => '2026-08-01',
            'lines' => [
                ['item_id' => $this->fertiliser->id, 'quantity' => 10, 'unit_cost' => 5000],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.total', '50000.00')
            ->assertJsonCount(1, 'data.lines');
    }

    public function test_a_purchase_order_needs_at_least_one_line(): void
    {
        $this->api()->postJson('/api/v1/purchase-orders', [
            'order_date' => '2026-08-01',
            'lines' => [],
        ])->assertStatus(422);
    }

    public function test_issuing_assigns_a_number_and_moves_it_to_issued(): void
    {
        $po = $this->createPurchaseOrder();

        $response = $this->api()->postJson("/api/v1/purchase-orders/{$po->id}/issue");

        $response->assertOk()->assertJsonPath('data.status', 'issued');
        $this->assertNotNull($response->json('data.number'));
        $this->assertStringStartsWith('PO-', $response->json('data.number'));
    }

    public function test_a_draft_cannot_be_received(): void
    {
        $po = $this->createPurchaseOrder();

        $this->api()->postJson("/api/v1/purchase-orders/{$po->id}/receive", [
            'lines' => [['purchase_order_line_id' => $po->lines()->sole()->id, 'quantity' => 10]],
        ])->assertStatus(409);
    }

    public function test_receiving_writes_stock_and_updates_status(): void
    {
        $po = $this->createPurchaseOrder();
        $this->api()->postJson("/api/v1/purchase-orders/{$po->id}/issue")->assertOk();
        $line = $po->lines()->sole();

        $response = $this->api()->postJson("/api/v1/purchase-orders/{$po->id}/receive", [
            'lines' => [
                ['purchase_order_line_id' => $line->id, 'quantity' => 10, 'batch_number' => 'FERT-2026-A'],
            ],
        ]);

        $response->assertOk()->assertJsonPath('data.status', 'received');

        $movement = StockMovement::query()->where('item_id', $this->fertiliser->id)->sole();
        $this->assertSame('10.000', $movement->quantity);
        $this->assertSame('purchase', $movement->reason);
        $this->assertSame('FERT-2026-A', $movement->batch_number);
        $this->assertSame(PurchaseOrder::class, $movement->reference_type);
        $this->assertSame($po->id, $movement->reference_id);
        $this->assertSame(10.0, (float) $this->fertiliser->fresh()->stockOnHand());
    }

    public function test_a_partial_receipt_leaves_the_order_partially_received(): void
    {
        $po = $this->createPurchaseOrder();
        $this->api()->postJson("/api/v1/purchase-orders/{$po->id}/issue")->assertOk();
        $line = $po->lines()->sole();

        $response = $this->api()->postJson("/api/v1/purchase-orders/{$po->id}/receive", [
            'lines' => [
                ['purchase_order_line_id' => $line->id, 'quantity' => 4],
            ],
        ]);

        $response->assertOk()->assertJsonPath('data.status', 'partially_received');

        $this->api()->postJson("/api/v1/purchase-orders/{$po->id}/receive", [
            'lines' => [
                ['purchase_order_line_id' => $line->id, 'quantity' => 6],
            ],
        ])->assertOk()->assertJsonPath('data.status', 'received');
    }

    public function test_editing_lines_on_a_draft_purchase_order_is_allowed(): void
    {
        $po = $this->createPurchaseOrder();

        $response = $this->api()->patchJson("/api/v1/purchase-orders/{$po->id}", [
            'lines' => [
                ['item_id' => $this->fertiliser->id, 'quantity' => 20, 'unit_cost' => 4500],
            ],
        ]);

        $response->assertOk()->assertJsonCount(1, 'data.lines');
        $this->assertSame('20.000', $po->fresh()->lines()->sole()->quantity);
    }

    public function test_editing_lines_on_an_issued_purchase_order_is_rejected(): void
    {
        $po = $this->createPurchaseOrder();
        $this->api()->postJson("/api/v1/purchase-orders/{$po->id}/issue")->assertOk();
        $originalLineId = $po->lines()->sole()->id;

        $response = $this->api()->patchJson("/api/v1/purchase-orders/{$po->id}", [
            'lines' => [
                ['item_id' => $this->fertiliser->id, 'quantity' => 99, 'unit_cost' => 1],
            ],
        ]);

        $response->assertStatus(422);
        $this->assertNotEmpty($response->json('error.details.lines'));
        $this->assertSame($originalLineId, $po->fresh()->lines()->sole()->id);
    }

    public function test_editing_lines_on_a_partially_received_purchase_order_is_rejected(): void
    {
        $po = $this->createPurchaseOrder();
        $this->api()->postJson("/api/v1/purchase-orders/{$po->id}/issue")->assertOk();
        $line = $po->lines()->sole();
        $this->api()->postJson("/api/v1/purchase-orders/{$po->id}/receive", [
            'lines' => [['purchase_order_line_id' => $line->id, 'quantity' => 4]],
        ])->assertOk()->assertJsonPath('data.status', 'partially_received');

        $response = $this->api()->patchJson("/api/v1/purchase-orders/{$po->id}", [
            'lines' => [
                ['item_id' => $this->fertiliser->id, 'quantity' => 99, 'unit_cost' => 1],
            ],
        ]);
        $response->assertStatus(422);
        $this->assertNotEmpty($response->json('error.details.lines'));
    }

    public function test_editing_lines_on_a_received_purchase_order_is_rejected(): void
    {
        $po = $this->createPurchaseOrder();
        $this->api()->postJson("/api/v1/purchase-orders/{$po->id}/issue")->assertOk();
        $line = $po->lines()->sole();
        $this->api()->postJson("/api/v1/purchase-orders/{$po->id}/receive", [
            'lines' => [['purchase_order_line_id' => $line->id, 'quantity' => 10]],
        ])->assertOk()->assertJsonPath('data.status', 'received');

        $response = $this->api()->patchJson("/api/v1/purchase-orders/{$po->id}", [
            'lines' => [
                ['item_id' => $this->fertiliser->id, 'quantity' => 99, 'unit_cost' => 1],
            ],
        ]);
        $response->assertStatus(422);
        $this->assertNotEmpty($response->json('error.details.lines'));
    }

    public function test_changing_supplier_on_an_issued_purchase_order_is_rejected(): void
    {
        $po = $this->createPurchaseOrder();
        $this->api()->postJson("/api/v1/purchase-orders/{$po->id}/issue")->assertOk();

        $otherSupplier = Contact::create(['name' => 'Other Supply Co', 'type' => 'supplier']);

        $response = $this->api()->patchJson("/api/v1/purchase-orders/{$po->id}", [
            'supplier_id' => $otherSupplier->id,
        ]);
        $response->assertStatus(422);
        $this->assertNotEmpty($response->json('error.details.supplier_id'));
    }

    public function test_notes_can_still_be_edited_on_an_issued_purchase_order(): void
    {
        $po = $this->createPurchaseOrder();
        $this->api()->postJson("/api/v1/purchase-orders/{$po->id}/issue")->assertOk();

        $this->api()->patchJson("/api/v1/purchase-orders/{$po->id}", [
            'notes' => 'Delivered to gate 3',
        ])->assertOk()->assertJsonPath('data.notes', 'Delivered to gate 3');
    }

    public function test_receiving_more_than_remaining_is_rejected(): void
    {
        $po = $this->createPurchaseOrder();
        $this->api()->postJson("/api/v1/purchase-orders/{$po->id}/issue")->assertOk();
        $line = $po->lines()->sole();

        $response = $this->api()->postJson("/api/v1/purchase-orders/{$po->id}/receive", [
            'lines' => [
                ['purchase_order_line_id' => $line->id, 'quantity' => 15],
            ],
        ]);

        $response->assertStatus(409);
        $this->assertSame('0.000', $line->fresh()->quantity_received);
    }

    public function test_receiving_more_than_what_remains_after_a_partial_receipt_is_rejected(): void
    {
        $po = $this->createPurchaseOrder();
        $this->api()->postJson("/api/v1/purchase-orders/{$po->id}/issue")->assertOk();
        $line = $po->lines()->sole();

        $this->api()->postJson("/api/v1/purchase-orders/{$po->id}/receive", [
            'lines' => [['purchase_order_line_id' => $line->id, 'quantity' => 4]],
        ])->assertOk()->assertJsonPath('data.status', 'partially_received');

        $this->api()->postJson("/api/v1/purchase-orders/{$po->id}/receive", [
            'lines' => [['purchase_order_line_id' => $line->id, 'quantity' => 7]],
        ])->assertStatus(409);

        $this->assertSame('4.000', $line->fresh()->quantity_received);
    }

    public function test_a_token_without_the_receive_ability_is_refused(): void
    {
        $po = $this->createPurchaseOrder();
        $this->api()->postJson("/api/v1/purchase-orders/{$po->id}/issue")->assertOk();
        $line = $po->lines()->sole();

        $limitedToken = app(ApiTokenIssuer::class)
            ->issue($this->owner, $this->company, 'view-only', ['procurement.view'])
            ->plainTextToken;

        Auth::forgetGuards();

        $this->withHeader('Authorization', "Bearer {$limitedToken}")
            ->postJson("/api/v1/purchase-orders/{$po->id}/receive", [
                'lines' => [['purchase_order_line_id' => $line->id, 'quantity' => 10]],
            ])->assertStatus(403);
    }

    public function test_purchase_orders_are_scoped_to_their_company(): void
    {
        $po = $this->createPurchaseOrder();

        $otherOwner = User::factory()->create();
        $otherCompany = Company::create([
            'slug' => 'other-'.Str::lower(Str::random(4)),
            'name' => 'Other Ltd',
            'owner_id' => $otherOwner->id,
            'currency' => 'USD',
        ]);
        $this->joinCompany($otherCompany, $otherOwner, Role::OWNER);
        app(CurrentCompany::class)->set($otherCompany);

        $otherToken = app(ApiTokenIssuer::class)
            ->issue($otherOwner, $otherCompany, 'test', ['*'])
            ->plainTextToken;

        Auth::forgetGuards();

        $this->withHeader('Authorization', "Bearer {$otherToken}")
            ->getJson("/api/v1/purchase-orders/{$po->id}")
            ->assertStatus(404);
    }
}
