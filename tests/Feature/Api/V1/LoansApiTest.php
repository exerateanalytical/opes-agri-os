<?php

namespace Tests\Feature\Api\V1;

use App\Models\Company;
use App\Models\Contact;
use App\Models\CooperativeMember;
use App\Models\JournalEntry;
use App\Models\Loan;
use App\Models\LoanRepayment;
use App\Models\Role;
use App\Models\User;
use App\Services\ApiTokenIssuer;
use App\Support\Accounting\ChartOfAccounts;
use App\Support\CurrentCompany;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Tests\TestCase;

class LoansApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected Company $company;

    protected CooperativeMember $member;

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
        ChartOfAccounts::seed($this->company);

        $contact = Contact::create(['name' => 'Jane Farmer']);
        $this->member = CooperativeMember::create(['contact_id' => $contact->id, 'status' => 'active']);

        $this->token = app(ApiTokenIssuer::class)
            ->issue($this->owner, $this->company, 'test', ['*'])
            ->plainTextToken;
    }

    protected function api()
    {
        return $this->withHeader('Authorization', "Bearer {$this->token}");
    }

    protected function createLoan(float $principal = 100000, float $rate = 0.10): Loan
    {
        $response = $this->api()->postJson('/api/v1/loans', [
            'cooperative_member_id' => $this->member->id,
            'principal' => $principal,
            'interest_rate' => $rate,
        ]);

        return Loan::findOrFail($response->json('data.id'));
    }

    public function test_it_creates_a_pending_loan(): void
    {
        $response = $this->api()->postJson('/api/v1/loans', [
            'cooperative_member_id' => $this->member->id,
            'principal' => 100000,
            'interest_rate' => 0.10,
        ]);

        $response->assertCreated()->assertJsonPath('data.status', 'pending');
    }

    public function test_disbursing_sets_totals_and_posts_to_the_ledger(): void
    {
        $loan = $this->createLoan();

        $response = $this->api()->postJson("/api/v1/loans/{$loan->id}/disburse", [
            'method' => 'cash',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.total_interest', '10000.00')
            ->assertJsonPath('data.total_repayable', '110000.00')
            ->assertJsonPath('data.balance', '110000.00');

        $entry = JournalEntry::query()->where('source_type', Loan::class)->where('source_id', $loan->id)->sole();
        $this->assertSame('CA', $entry->journal);
        $this->assertSame(100000.0, (float) $entry->lines()->sum('debit'));
    }

    public function test_a_repayment_splits_between_principal_and_interest_and_posts(): void
    {
        $loan = $this->createLoan(principal: 100000, rate: 0.10);
        $this->api()->postJson("/api/v1/loans/{$loan->id}/disburse", ['method' => 'cash'])->assertOk();

        $response = $this->api()->postJson("/api/v1/loans/{$loan->id}/repayments", [
            'amount' => 11000,
            'method' => 'cash',
        ]);

        $response->assertOk()->assertJsonPath('data.balance', '99000.00');

        $repayment = $loan->fresh()->repayments()->sole();
        // interest ratio = 10000/110000; 11000 * ratio = 1000.00 exactly.
        $this->assertSame('1000.00', $repayment->interest_portion);
        $this->assertSame('10000.00', $repayment->principal_portion);
    }

    public function test_a_full_repayment_closes_the_loan(): void
    {
        $loan = $this->createLoan(principal: 50000, rate: 0.10);
        $this->api()->postJson("/api/v1/loans/{$loan->id}/disburse", ['method' => 'cash'])->assertOk();

        $this->api()->postJson("/api/v1/loans/{$loan->id}/repayments", [
            'amount' => 55000,
            'method' => 'cash',
        ])->assertOk()->assertJsonPath('data.status', 'closed')->assertJsonPath('data.balance', '0.00');
    }

    public function test_overpaying_a_loan_is_refused(): void
    {
        $loan = $this->createLoan(principal: 50000, rate: 0.10);
        $this->api()->postJson("/api/v1/loans/{$loan->id}/disburse", ['method' => 'cash'])->assertOk();

        $this->api()->postJson("/api/v1/loans/{$loan->id}/repayments", [
            'amount' => 100000,
            'method' => 'cash',
        ])->assertStatus(409);
    }

    public function test_a_duplicate_repayment_submission_with_the_same_reference_is_idempotent(): void
    {
        $loan = $this->createLoan(principal: 100000, rate: 0.10);
        $this->api()->postJson("/api/v1/loans/{$loan->id}/disburse", ['method' => 'cash'])->assertOk();

        $payload = [
            'amount' => 11000,
            'method' => 'cash',
            'reference' => 'MOBILE-TXN-4471',
        ];

        $first = $this->api()->postJson("/api/v1/loans/{$loan->id}/repayments", $payload);
        $first->assertOk()->assertJsonPath('data.balance', '99000.00');

        // Same idempotency key, same loan, same amount — simulates a
        // client retry (e.g. a timed-out response resubmitted).
        $second = $this->api()->postJson("/api/v1/loans/{$loan->id}/repayments", $payload);
        $second->assertOk()->assertJsonPath('data.balance', '99000.00');

        $this->assertSame(1, $loan->fresh()->repayments()->count());
        $this->assertSame(
            1,
            JournalEntry::query()->where('source_type', LoanRepayment::class)->count()
        );
    }

    public function test_a_pending_loan_cannot_take_a_repayment(): void
    {
        $loan = $this->createLoan();

        $this->api()->postJson("/api/v1/loans/{$loan->id}/repayments", [
            'amount' => 1000,
            'method' => 'cash',
        ])->assertStatus(409);
    }

    public function test_a_token_without_the_disburse_ability_is_refused(): void
    {
        $loan = $this->createLoan();

        $limitedToken = app(ApiTokenIssuer::class)
            ->issue($this->owner, $this->company, 'limited', ['cooperative.view', 'cooperative.create'])
            ->plainTextToken;

        Auth::forgetGuards();

        $this->withHeader('Authorization', "Bearer {$limitedToken}")
            ->postJson("/api/v1/loans/{$loan->id}/disburse", ['method' => 'cash'])
            ->assertStatus(403);
    }

    public function test_loans_are_scoped_to_their_company(): void
    {
        $loan = $this->createLoan();

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
            ->getJson("/api/v1/loans/{$loan->id}")
            ->assertStatus(404);
    }
}
