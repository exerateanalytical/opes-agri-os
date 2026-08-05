<?php

namespace Tests\Feature;

use App\Livewire\Grants\Index as GrantsIndex;
use App\Livewire\Grants\RecordTransaction;
use App\Models\Company;
use App\Models\GrantProject;
use App\Models\JournalEntry;
use App\Models\Role;
use App\Models\User;
use App\Support\Accounting\ChartOfAccounts;
use App\Support\CurrentCompany;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
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
}
