<?php

namespace Tests\Feature;

use App\Livewire\Cooperative\Index as CooperativeIndex;
use App\Models\Company;
use App\Models\Contact;
use App\Models\CooperativeMember;
use App\Models\JournalEntry;
use App\Models\Loan;
use App\Models\Role;
use App\Models\User;
use App\Support\Accounting\ChartOfAccounts;
use App\Support\CurrentCompany;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class LoansTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected Company $company;

    protected CooperativeMember $member;

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
    }

    public function test_it_creates_and_disburses_a_loan(): void
    {
        Livewire::actingAs($this->owner)
            ->test(CooperativeIndex::class)
            ->call('startAddingLoan')
            ->set('loanMemberId', $this->member->id)
            ->set('loanPrincipal', '100000')
            ->set('loanInterestRate', '0.10')
            ->call('saveLoan')
            ->assertHasNoErrors();

        $loan = Loan::query()->where('company_id', $this->company->id)->sole();
        $this->assertSame('pending', $loan->status);

        Livewire::actingAs($this->owner)
            ->test(CooperativeIndex::class)
            ->call('disburseLoan', $loan->id)
            ->assertHasNoErrors();

        $loan->refresh();
        $this->assertSame('active', $loan->status);
        $this->assertSame('110000.00', $loan->balance);
        $this->assertSame(1, JournalEntry::query()->where('source_type', Loan::class)->where('source_id', $loan->id)->count());
    }

    public function test_recording_a_repayment_reduces_the_balance(): void
    {
        $loan = Loan::create([
            'cooperative_member_id' => $this->member->id, 'principal' => 50000, 'interest_rate' => 0.10,
            'total_interest' => 5000, 'total_repayable' => 55000, 'balance' => 55000, 'status' => 'active',
            'disbursed_on' => now(),
        ]);

        Livewire::actingAs($this->owner)
            ->test(CooperativeIndex::class)
            ->call('openRepayment', $loan->id)
            ->set('repaymentAmount', '55000')
            ->set('repaymentMethod', 'cash')
            ->call('saveRepayment')
            ->assertHasNoErrors();

        $this->assertSame('closed', $loan->fresh()->status);
        $this->assertSame('0.00', $loan->fresh()->balance);
    }
}
