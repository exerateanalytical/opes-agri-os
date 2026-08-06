<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Livewire\Grants\Index as GrantsIndex;
use App\Livewire\Grants\RecordTransaction;
use App\Models\Company;
use App\Models\GrantProject;
use App\Models\GrantTransaction;
use App\Models\JournalEntry;
use App\Models\LedgerAccount;
use App\Models\Role;
use App\Models\User;
use App\Services\Grants\GrantTransactionRecorder;
use App\Support\Accounting\ChartOfAccounts;
use App\Support\CurrentCompany;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class GrantsTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected Company $company;

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
    }

    public function test_it_creates_a_grant_project(): void
    {
        Livewire::actingAs($this->owner)
            ->test(GrantsIndex::class)
            ->call('startAdding')
            ->set('name', 'Water Access Project')
            ->set('totalAmount', '50000')
            ->call('save')
            ->assertHasNoErrors();

        $project = GrantProject::query()->where('company_id', $this->company->id)->sole();
        $this->assertSame('planned', $project->status);
    }

    public function test_recording_a_receipt_posts_to_the_ledger_and_updates_the_running_total(): void
    {
        $project = GrantProject::create(['name' => 'Water Access Project', 'total_amount' => 50000, 'currency' => 'USD', 'status' => 'active']);

        Livewire::actingAs($this->owner)
            ->test(RecordTransaction::class)
            ->call('open', $project->id)
            ->set('type', 'receipt')
            ->set('amount', '20000')
            ->set('method', 'bank_transfer')
            ->call('save')
            ->assertHasNoErrors();

        $project->refresh();
        $this->assertSame('20000.00', (string) $project->received_amount);
        $this->assertSame(1, JournalEntry::query()->count());
    }

    public function test_an_expenditure_larger_than_the_grant_balance_is_refused(): void
    {
        $project = GrantProject::create(['name' => 'Water Access Project', 'total_amount' => 50000, 'currency' => 'USD', 'status' => 'active']);

        Livewire::actingAs($this->owner)
            ->test(RecordTransaction::class)
            ->call('open', $project->id)
            ->set('type', 'expenditure')
            ->set('amount', '500')
            ->set('method', 'cash')
            ->call('save')
            ->assertHasErrors('amount');
    }

    public function test_a_disabled_grants_module_hides_the_page(): void
    {
        $this->company->forceFill(['modules' => ['grants' => false]])->save();

        Livewire::actingAs($this->owner)->test(GrantsIndex::class)->assertForbidden();
    }

    public function test_the_overspend_guard_is_re_checked_against_a_locked_refetch(): void
    {
        // Mirrors the lock-refetch pattern used by PaymentRecorder/DocumentIssuer:
        // the balance check must be evaluated against a freshly locked row, not
        // an in-memory instance captured before the transaction opened. Simulate
        // a stale in-memory project (as if read before a concurrent expenditure
        // already consumed the balance) and confirm the guard still catches it
        // because it re-fetches under lockForUpdate() inside the transaction.
        $project = GrantProject::create(['name' => 'Water Access Project', 'total_amount' => 50000, 'currency' => 'USD', 'status' => 'active']);
        $project->increment('received_amount', 1000);

        $stale = GrantProject::find($project->id);
        $this->assertSame(1000.0, $stale->balance());

        // Someone else spends the whole balance after $stale was read.
        app(GrantTransactionRecorder::class)->record(
            $project->fresh(),
            $this->owner,
            'expenditure',
            1000.0,
            PaymentMethod::Cash,
        );

        // $stale still reports the pre-spend balance in memory, but the
        // recorder must recompute from a locked refetch, not from $stale.
        $this->assertSame(1000.0, $stale->balance());

        $this->expectException(RuntimeException::class);

        app(GrantTransactionRecorder::class)->record(
            $stale,
            $this->owner,
            'expenditure',
            500.0,
            PaymentMethod::Cash,
        );
    }

    public function test_a_ledger_posting_failure_rolls_back_the_grant_transaction_and_cached_totals(): void
    {
        $project = GrantProject::create(['name' => 'Water Access Project', 'total_amount' => 50000, 'currency' => 'USD', 'status' => 'active']);

        // Misconfigure the chart: keep 'receivables' (411) so chartIsReady()
        // still passes, but remove 'grant_income' (741) so Ledger::post()
        // throws when it tries to resolve that account.
        LedgerAccount::query()->withoutGlobalScopes()->where('company_id', $this->company->id)->where('number', '741')->delete();

        try {
            app(GrantTransactionRecorder::class)->record(
                $project,
                $this->owner,
                'receipt',
                5000.0,
                PaymentMethod::BankTransfer,
            );
            $this->fail('Expected a ledger posting failure to propagate.');
        } catch (RuntimeException $e) {
            // expected — the missing account throws inside the transaction
        }

        $project->refresh();
        $this->assertSame('0.00', (string) $project->received_amount);
        $this->assertSame(0, GrantTransaction::query()->where('grant_project_id', $project->id)->count());
        $this->assertSame(0, JournalEntry::query()->count());
    }

    public function test_a_transaction_currency_mismatch_is_rejected(): void
    {
        $project = GrantProject::create(['name' => 'Water Access Project', 'total_amount' => 50000, 'currency' => 'USD', 'status' => 'active']);

        $this->expectException(RuntimeException::class);

        app(GrantTransactionRecorder::class)->record(
            $project,
            $this->owner,
            'receipt',
            1000.0,
            PaymentMethod::BankTransfer,
            null,
            ['currency' => 'EUR'],
        );
    }
}
