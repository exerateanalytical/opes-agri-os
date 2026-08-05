<?php

namespace Tests\Feature\Api\V1;

use App\Models\Company;
use App\Models\Item;
use App\Models\Role;
use App\Models\User;
use App\Services\ApiTokenIssuer;
use App\Services\Stock\StockLedger;
use App\Support\CurrentCompany;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class BatchTraceApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected Company $company;

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

        $this->maize = Item::create(['name' => 'Maize', 'type' => 'product', 'track_stock' => true, 'unit' => 'bag']);

        app(StockLedger::class)->receive(
            company: $this->company,
            item: $this->maize,
            quantity: 100,
            unitCost: 5,
            batchNumber: 'MAIZE-2026-A',
        );
        app(StockLedger::class)->receive(
            company: $this->company,
            item: $this->maize,
            quantity: -20,
            reason: 'sale',
            batchNumber: 'MAIZE-2026-A',
        );

        $this->token = app(ApiTokenIssuer::class)
            ->issue($this->owner, $this->company, 'test', ['*'])
            ->plainTextToken;
    }

    protected function api()
    {
        return $this->withHeader('Authorization', "Bearer {$this->token}");
    }

    public function test_it_traces_every_movement_for_a_batch_in_order(): void
    {
        $response = $this->api()->getJson("/api/v1/items/{$this->maize->id}/batches/MAIZE-2026-A/trace");

        $response->assertOk()->assertJsonCount(2, 'data');
        $this->assertSame('100.000', $response->json('data.0.quantity'));
        $this->assertSame('purchase', $response->json('data.0.reason'));
        $this->assertSame('-20.000', $response->json('data.1.quantity'));
        $this->assertSame('sale', $response->json('data.1.reason'));
    }

    public function test_an_unknown_batch_returns_no_movements(): void
    {
        $this->api()->getJson("/api/v1/items/{$this->maize->id}/batches/NOTHING/trace")
            ->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_batch_traces_are_scoped_to_their_company(): void
    {
        $otherOwner = User::factory()->create();
        $otherCompany = Company::create([
            'slug' => 'other-'.Str::lower(Str::random(4)),
            'name' => 'Other Ltd',
            'owner_id' => $otherOwner->id,
            'currency' => 'USD',
        ]);
        app(CurrentCompany::class)->set($otherCompany);
        $theirItem = Item::create(['name' => 'Theirs', 'type' => 'product', 'track_stock' => true]);

        $this->api()->getJson("/api/v1/items/{$theirItem->id}/batches/MAIZE-2026-A/trace")->assertStatus(404);
    }
}
