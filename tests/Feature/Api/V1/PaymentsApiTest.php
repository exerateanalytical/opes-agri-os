<?php

namespace Tests\Feature\Api\V1;

use App\Enums\DocumentType;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Document;
use App\Models\Payment;
use App\Models\Role;
use App\Models\User;
use App\Services\ApiTokenIssuer;
use App\Support\CurrentCompany;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaymentsApiTest extends TestCase
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

    protected function issuedInvoice(float $total = 200): string
    {
        $create = $this->api()->postJson('/api/v1/documents', [
            'type' => DocumentType::Invoice->value,
            'contact_id' => $this->contact->id,
            'issue_date' => now()->toDateString(),
            'lines' => [
                ['description' => 'Goods', 'quantity' => 1, 'unit_price' => $total],
            ],
        ]);
        $id = $create->json('data.id');
        $this->api()->postJson("/api/v1/documents/{$id}/issue");

        return $id;
    }

    public function test_it_records_a_payment_and_issues_a_receipt(): void
    {
        $documentId = $this->issuedInvoice(200);

        $response = $this->api()->postJson("/api/v1/documents/{$documentId}/payments", [
            'amount' => 200,
            'method' => 'cash',
            'reference' => 'CASH-001',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.amount', '200.00')
            ->assertJsonPath('data.method', 'cash')
            ->assertJsonPath('data.receipt.status', 'issued')
            ->assertJsonPath('data.receipt.verification_url', fn ($url) => is_string($url) && str_contains($url, '/v/'));

        $this->assertSame('paid', Document::find($documentId)->status->value);
    }

    public function test_an_overpayment_is_rejected_as_a_conflict(): void
    {
        $documentId = $this->issuedInvoice(200);

        $this->api()->postJson("/api/v1/documents/{$documentId}/payments", [
            'amount' => 500,
            'method' => 'cash',
        ])->assertStatus(409);
    }

    public function test_a_payment_cannot_be_recorded_against_a_draft(): void
    {
        $create = $this->api()->postJson('/api/v1/documents', [
            'type' => DocumentType::Invoice->value,
            'contact_id' => $this->contact->id,
            'issue_date' => now()->toDateString(),
            'lines' => [['description' => 'Goods', 'quantity' => 1, 'unit_price' => 100]],
        ]);
        $documentId = $create->json('data.id');

        $this->api()->postJson("/api/v1/documents/{$documentId}/payments", [
            'amount' => 100,
            'method' => 'cash',
        ])->assertStatus(409);
    }

    public function test_it_lists_and_shows_payments(): void
    {
        $documentId = $this->issuedInvoice(200);
        $this->api()->postJson("/api/v1/documents/{$documentId}/payments", ['amount' => 200, 'method' => 'cash']);

        $payment = Payment::query()->where('company_id', $this->company->id)->sole();

        $this->api()->getJson('/api/v1/payments')
            ->assertOk()
            ->assertJsonPath('data.0.id', $payment->id);

        $this->api()->getJson("/api/v1/payments/{$payment->id}")
            ->assertOk()
            ->assertJsonPath('data.amount', '200.00');
    }

    public function test_a_token_without_the_record_ability_is_refused(): void
    {
        $documentId = $this->issuedInvoice(200);

        $viewOnlyToken = app(ApiTokenIssuer::class)
            ->issue($this->owner, $this->company, 'view-only', ['payments.view'])
            ->plainTextToken;

        // Sanctum's guard memoizes the resolved user on the guard instance for
        // the life of the test method, not per simulated request — without
        // this, the $this->token calls above leave the owner's identity
        // cached and this request never actually re-resolves $viewOnlyToken.
        Auth::forgetGuards();

        $this->withHeader('Authorization', "Bearer {$viewOnlyToken}")
            ->postJson("/api/v1/documents/{$documentId}/payments", ['amount' => 200, 'method' => 'cash'])
            ->assertStatus(403);
    }
}
