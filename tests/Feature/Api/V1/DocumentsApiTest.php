<?php

namespace Tests\Feature\Api\V1;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Document;
use App\Models\Item;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\ApiTokenIssuer;
use App\Services\Stock\StockLedger;
use App\Support\CurrentCompany;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DocumentsApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected Company $company;

    protected Contact $contact;

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
        $this->contact = Contact::create(['name' => 'A Customer']);

        $this->token = app(ApiTokenIssuer::class)
            ->issue($this->owner, $this->company, 'test', ['*'])
            ->plainTextToken;
    }

    protected function api()
    {
        return $this->withHeader('Authorization', "Bearer {$this->token}");
    }

    protected function draftPayload(array $overrides = []): array
    {
        return array_merge([
            'type' => DocumentType::Quotation->value,
            'contact_id' => $this->contact->id,
            'issue_date' => now()->toDateString(),
            'lines' => [
                ['description' => 'Consulting', 'quantity' => 2, 'unit_price' => 100],
            ],
        ], $overrides);
    }

    public function test_it_creates_a_draft_with_computed_totals(): void
    {
        $response = $this->api()->postJson('/api/v1/documents', $this->draftPayload());

        $response->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.number', null)
            ->assertJsonPath('data.total', '200.00')
            ->assertJsonPath('data.lines.0.description', 'Consulting');
    }

    public function test_it_rejects_a_draft_with_no_lines(): void
    {
        $this->api()->postJson('/api/v1/documents', $this->draftPayload(['lines' => []]))
            ->assertStatus(422)
            ->assertJsonPath('error.details.lines.0', 'The lines field is required.');
    }

    public function test_it_rejects_a_contact_from_another_company(): void
    {
        $otherOwner = User::factory()->create();
        $otherCompany = Company::create([
            'slug' => 'other-'.Str::lower(Str::random(4)),
            'name' => 'Other Ltd',
            'owner_id' => $otherOwner->id,
            'currency' => 'USD',
        ]);
        app(CurrentCompany::class)->set($otherCompany);
        $foreignContact = Contact::create(['name' => 'Theirs']);

        $this->api()->postJson('/api/v1/documents', $this->draftPayload(['contact_id' => $foreignContact->id]))
            ->assertStatus(422)
            ->assertJsonPath('error.details.contact_id.0', 'The selected contact id is invalid.');
    }

    public function test_it_issues_a_draft_and_assigns_a_number(): void
    {
        $create = $this->api()->postJson('/api/v1/documents', $this->draftPayload(['type' => DocumentType::Invoice->value]));
        $id = $create->json('data.id');

        $response = $this->api()->postJson("/api/v1/documents/{$id}/issue");

        $response->assertOk()
            ->assertJsonPath('data.status', 'issued')
            ->assertJsonPath('data.verification_url', fn ($url) => is_string($url) && str_contains($url, '/v/'));

        $this->assertNotNull(Document::find($id)->number);
    }

    public function test_issuing_twice_is_a_conflict_not_a_500(): void
    {
        $create = $this->api()->postJson('/api/v1/documents', $this->draftPayload(['type' => DocumentType::Invoice->value]));
        $id = $create->json('data.id');

        $this->api()->postJson("/api/v1/documents/{$id}/issue")->assertOk();

        $this->api()->postJson("/api/v1/documents/{$id}/issue")
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'conflict');
    }

    public function test_it_voids_an_issued_document(): void
    {
        $create = $this->api()->postJson('/api/v1/documents', $this->draftPayload(['type' => DocumentType::Invoice->value]));
        $id = $create->json('data.id');
        $this->api()->postJson("/api/v1/documents/{$id}/issue");

        $this->api()->postJson("/api/v1/documents/{$id}/void", ['reason' => 'Customer changed their mind'])
            ->assertOk()
            ->assertJsonPath('data.status', 'void');
    }

    public function test_it_converts_a_quotation_into_an_invoice(): void
    {
        $create = $this->api()->postJson('/api/v1/documents', $this->draftPayload());
        $id = $create->json('data.id');
        $this->api()->postJson("/api/v1/documents/{$id}/issue");

        $response = $this->api()->postJson("/api/v1/documents/{$id}/convert");

        // Laravel infers 201 automatically here (Document::wasRecentlyCreated
        // is true inside DocumentConverter::convert()) — conversion really
        // does mint a new document, so this is the right code, not a quirk
        // to work around.
        $response->assertCreated()
            ->assertJsonPath('data.type', 'invoice')
            ->assertJsonPath('data.status', 'issued')
            ->assertJsonPath('data.parent_document_id', $id);
    }

    public function test_a_draft_cannot_be_converted(): void
    {
        $create = $this->api()->postJson('/api/v1/documents', $this->draftPayload());
        $id = $create->json('data.id');

        $this->api()->postJson("/api/v1/documents/{$id}/convert")
            ->assertStatus(409);
    }

    public function test_issued_document_metadata_cannot_be_edited(): void
    {
        $create = $this->api()->postJson('/api/v1/documents', $this->draftPayload(['type' => DocumentType::Invoice->value]));
        $id = $create->json('data.id');
        $this->api()->postJson("/api/v1/documents/{$id}/issue");

        $this->api()->patchJson("/api/v1/documents/{$id}", ['notes' => 'sneaky edit'])
            ->assertStatus(409);
    }

    /*
     * A sales line with no item_id never reached StockLedger::move() — nothing
     * in the store/create path accepted or persisted it, so a real invoice
     * issued through the API never moved stock no matter what it sold. This
     * goes through the actual create + issue endpoints, not a hand-crafted
     * DocumentLine::create(), because that is exactly the path that was
     * broken.
     */
    public function test_issuing_a_real_invoice_with_a_tracked_item_line_moves_stock(): void
    {
        $item = Item::create([
            'company_id' => $this->company->id,
            'name' => 'Ciment 50kg',
            'sku' => 'CIM-50',
            'type' => 'product',
            'price' => 6500,
            'track_stock' => true,
            'is_active' => true,
        ]);

        app(StockLedger::class)->receive(
            $this->company, $item, 100, 5000, null, $this->owner
        );

        $create = $this->api()->postJson('/api/v1/documents', $this->draftPayload([
            'type' => DocumentType::Invoice->value,
            'lines' => [
                ['item_id' => $item->id, 'description' => 'Ciment 50kg', 'quantity' => 12, 'unit_price' => 6500],
            ],
        ]));

        $create->assertCreated()->assertJsonPath('data.lines.0.item_id', $item->id);
        $id = $create->json('data.id');

        $this->api()->postJson("/api/v1/documents/{$id}/issue")->assertOk();

        $this->assertSame(88.0, $item->fresh()->stockOnHand());

        $this->assertDatabaseHas('stock_movements', [
            'item_id' => $item->id,
            'reason' => 'sale',
            'reference_type' => Document::class,
            'reference_id' => $id,
        ]);

        $movement = StockMovement::query()->withoutGlobalScopes()
            ->where('reference_id', $id)->where('reason', 'sale')->firstOrFail();

        $this->assertSame(-12.0, (float) $movement->quantity);
    }

    public function test_a_line_with_an_item_from_another_company_is_rejected(): void
    {
        $otherOwner = User::factory()->create();
        $otherCompany = Company::create([
            'slug' => 'other-'.Str::lower(Str::random(4)),
            'name' => 'Other Ltd',
            'owner_id' => $otherOwner->id,
            'currency' => 'USD',
        ]);
        app(CurrentCompany::class)->set($otherCompany);
        $foreignItem = Item::create([
            'company_id' => $otherCompany->id,
            'name' => 'Not Yours',
            'type' => 'product',
            'price' => 10,
            'track_stock' => false,
            'is_active' => true,
        ]);
        app(CurrentCompany::class)->set($this->company);

        $this->api()->postJson('/api/v1/documents', $this->draftPayload([
            'lines' => [
                ['item_id' => $foreignItem->id, 'description' => 'x', 'quantity' => 1, 'unit_price' => 10],
            ],
        ]))->assertStatus(422);
    }

    public function test_a_view_only_token_cannot_issue(): void
    {
        app(CurrentCompany::class)->set($this->company);
        $document = Document::create([
            'type' => DocumentType::Invoice,
            'contact_id' => $this->contact->id,
            'status' => DocumentStatus::Draft,
            'issue_date' => now()->toDateString(),
            'currency' => 'USD',
            'subtotal' => 100,
            'total' => 100,
            'balance' => 100,
        ]);

        $viewOnlyToken = app(ApiTokenIssuer::class)
            ->issue($this->owner, $this->company, 'view-only', ['sales.view'])
            ->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$viewOnlyToken}")
            ->postJson("/api/v1/documents/{$document->id}/issue")
            ->assertStatus(403);
    }
}
