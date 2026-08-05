<?php

namespace Tests\Feature\Api\V1;

use App\Models\Company;
use App\Models\GrantProject;
use App\Models\JournalEntry;
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

class GrantProjectsApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected Company $company;

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

        $this->token = app(ApiTokenIssuer::class)
            ->issue($this->owner, $this->company, 'test', ['*'])
            ->plainTextToken;
    }

    protected function api()
    {
        return $this->withHeader('Authorization', "Bearer {$this->token}");
    }

    public function test_it_creates_a_grant_project(): void
    {
        $response = $this->api()->postJson('/api/v1/grant-projects', [
            'name' => 'Water Access Project',
            'total_amount' => 50000,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'planned')
            ->assertJsonPath('data.total_amount', '50000.00');
    }

    public function test_it_records_a_receipt_and_posts_it_to_the_ledger(): void
    {
        app(CurrentCompany::class)->set($this->company);
        $project = GrantProject::create(['name' => 'Water Access', 'total_amount' => 50000, 'currency' => 'USD', 'status' => 'active']);

        $response = $this->api()->postJson("/api/v1/grant-projects/{$project->id}/transactions", [
            'type' => 'receipt',
            'amount' => 20000,
            'method' => 'bank_transfer',
            'transaction_date' => '2026-08-01',
        ]);

        $response->assertOk()->assertJsonPath('data.received_amount', '20000.00');
        $this->assertSame(1, JournalEntry::query()->count());
    }

    public function test_it_records_an_expenditure_within_the_grant_balance(): void
    {
        app(CurrentCompany::class)->set($this->company);
        $project = GrantProject::create([
            'name' => 'Water Access', 'total_amount' => 50000, 'currency' => 'USD', 'status' => 'active',
            'received_amount' => 20000,
        ]);

        $response = $this->api()->postJson("/api/v1/grant-projects/{$project->id}/transactions", [
            'type' => 'expenditure',
            'amount' => 5000,
            'method' => 'cash',
        ]);

        $response->assertOk()->assertJsonPath('data.spent_amount', '5000.00');
    }

    public function test_a_token_without_the_record_transaction_ability_is_refused(): void
    {
        app(CurrentCompany::class)->set($this->company);
        $project = GrantProject::create(['name' => 'Water Access', 'total_amount' => 50000, 'currency' => 'USD', 'status' => 'active']);

        $limitedToken = app(ApiTokenIssuer::class)
            ->issue($this->owner, $this->company, 'view-only', ['grants.view'])
            ->plainTextToken;

        Auth::forgetGuards();

        $this->withHeader('Authorization', "Bearer {$limitedToken}")
            ->postJson("/api/v1/grant-projects/{$project->id}/transactions", ['type' => 'receipt', 'amount' => 100, 'method' => 'cash'])
            ->assertStatus(403);
    }

    public function test_grant_projects_are_scoped_to_their_company(): void
    {
        $otherOwner = User::factory()->create();
        $otherCompany = Company::create([
            'slug' => 'other-'.Str::lower(Str::random(4)),
            'name' => 'Other Ltd',
            'owner_id' => $otherOwner->id,
            'currency' => 'USD',
        ]);
        app(CurrentCompany::class)->set($otherCompany);
        $theirProject = GrantProject::create(['name' => 'Theirs', 'total_amount' => 1000, 'currency' => 'USD', 'status' => 'planned']);

        $this->api()->getJson("/api/v1/grant-projects/{$theirProject->id}")->assertStatus(404);
    }
}
