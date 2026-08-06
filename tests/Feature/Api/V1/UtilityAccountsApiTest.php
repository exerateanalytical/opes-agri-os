<?php

namespace Tests\Feature\Api\V1;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Models\UtilityAccount;
use App\Services\ApiTokenIssuer;
use App\Support\CurrentCompany;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Tests\TestCase;

class UtilityAccountsApiTest extends TestCase
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

        $this->token = app(ApiTokenIssuer::class)
            ->issue($this->owner, $this->company, 'test', ['*'])
            ->plainTextToken;
    }

    protected function api()
    {
        return $this->withHeader('Authorization', "Bearer {$this->token}");
    }

    public function test_it_creates_a_utility_account(): void
    {
        $response = $this->api()->postJson('/api/v1/utility-accounts', [
            'utility_type' => 'electricity',
            'provider_name' => 'ENEO',
            'account_number' => 'ENE-001',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.utility_type', 'electricity');
    }

    public function test_it_records_a_reading(): void
    {
        app(CurrentCompany::class)->set($this->company);
        $account = UtilityAccount::create(['utility_type' => 'water', 'status' => 'active']);

        $response = $this->api()->postJson("/api/v1/utility-accounts/{$account->id}/readings", [
            'read_on' => '2026-08-01',
            'meter_reading' => 1200,
            'consumption' => 45,
            'cost' => 15000,
        ]);

        $response->assertCreated()->assertJsonPath('data.consumption', '45.00');

        $this->api()->getJson("/api/v1/utility-accounts/{$account->id}/readings")
            ->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_it_rejects_a_backdated_reading(): void
    {
        app(CurrentCompany::class)->set($this->company);
        $account = UtilityAccount::create(['utility_type' => 'water', 'status' => 'active']);
        $account->readings()->create(['read_on' => '2026-08-10', 'meter_reading' => 100]);

        $this->api()->postJson("/api/v1/utility-accounts/{$account->id}/readings", [
            'read_on' => '2026-08-01',
            'meter_reading' => 110,
        ])->assertStatus(422)->assertJsonPath('error.details.read_on.0', fn ($m) => str_contains($m, 'in order'));
    }

    public function test_it_rejects_a_decreasing_meter_reading_without_a_reset(): void
    {
        app(CurrentCompany::class)->set($this->company);
        $account = UtilityAccount::create(['utility_type' => 'water', 'status' => 'active']);
        $account->readings()->create(['read_on' => '2026-08-01', 'meter_reading' => 100]);

        $this->api()->postJson("/api/v1/utility-accounts/{$account->id}/readings", [
            'read_on' => '2026-08-10',
            'meter_reading' => 50,
        ])->assertStatus(422)->assertJsonPath('error.details.meter_reading.0', fn ($m) => str_contains($m, 'reset'));
    }

    public function test_it_allows_a_decreasing_meter_reading_when_flagged_as_a_reset(): void
    {
        app(CurrentCompany::class)->set($this->company);
        $account = UtilityAccount::create(['utility_type' => 'water', 'status' => 'active']);
        $account->readings()->create(['read_on' => '2026-08-01', 'meter_reading' => 100]);

        $this->api()->postJson("/api/v1/utility-accounts/{$account->id}/readings", [
            'read_on' => '2026-08-10',
            'meter_reading' => 5,
            'meter_reset' => true,
        ])->assertCreated();
    }

    public function test_a_token_without_the_record_reading_ability_is_refused(): void
    {
        app(CurrentCompany::class)->set($this->company);
        $account = UtilityAccount::create(['utility_type' => 'water', 'status' => 'active']);

        $limitedToken = app(ApiTokenIssuer::class)
            ->issue($this->owner, $this->company, 'view-only', ['utilities.view'])
            ->plainTextToken;

        Auth::forgetGuards();

        $this->withHeader('Authorization', "Bearer {$limitedToken}")
            ->postJson("/api/v1/utility-accounts/{$account->id}/readings", ['read_on' => '2026-08-01'])
            ->assertStatus(403);
    }

    public function test_utility_accounts_are_scoped_to_their_company(): void
    {
        $otherOwner = User::factory()->create();
        $otherCompany = Company::create([
            'slug' => 'other-'.Str::lower(Str::random(4)),
            'name' => 'Other Ltd',
            'owner_id' => $otherOwner->id,
            'currency' => 'USD',
        ]);
        app(CurrentCompany::class)->set($otherCompany);
        $theirAccount = UtilityAccount::create(['utility_type' => 'electricity', 'status' => 'active']);

        $this->api()->getJson("/api/v1/utility-accounts/{$theirAccount->id}")->assertStatus(404);
    }
}
