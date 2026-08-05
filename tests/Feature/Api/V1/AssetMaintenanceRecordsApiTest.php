<?php

namespace Tests\Feature\Api\V1;

use App\Models\Company;
use App\Models\FixedAsset;
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

class AssetMaintenanceRecordsApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected Company $company;

    protected FixedAsset $asset;

    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->owner = User::factory()->create();
        $this->company = Company::create([
            'slug' => 'acme-'.Str::lower(Str::random(4)),
            'name' => 'Acme Sarl',
            'owner_id' => $this->owner->id,
            'currency' => 'XAF',
            'plan' => 'business',
            'account_type' => 'active',
        ]);

        $this->joinCompany($this->company, $this->owner, Role::OWNER);
        app(CurrentCompany::class)->set($this->company);
        ChartOfAccounts::seed($this->company);

        $this->asset = FixedAsset::create([
            'name' => 'Delivery Van', 'category' => 'vehicles', 'acquired_on' => '2026-01-01',
            'cost' => 8000000, 'residual_value' => 0, 'method' => 'straight_line',
            'useful_life_months' => 48, 'status' => 'active',
        ]);

        $this->token = app(ApiTokenIssuer::class)
            ->issue($this->owner, $this->company, 'test', ['*'])
            ->plainTextToken;
    }

    protected function api()
    {
        return $this->withHeader('Authorization', "Bearer {$this->token}");
    }

    public function test_it_records_maintenance(): void
    {
        $response = $this->api()->postJson("/api/v1/fixed-assets/{$this->asset->id}/maintenance-records", [
            'maintenance_type' => 'service',
            'description' => 'Oil change and filter',
            'performed_on' => '2026-08-01',
            'cost' => 25000,
            'next_due_on' => '2027-02-01',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.maintenance_type', 'service')
            ->assertJsonPath('data.cost', '25000.00');

        $this->api()->getJson("/api/v1/fixed-assets/{$this->asset->id}/maintenance-records")
            ->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_a_token_without_the_record_maintenance_ability_is_refused(): void
    {
        $limitedToken = app(ApiTokenIssuer::class)
            ->issue($this->owner, $this->company, 'view-only', ['assets.view'])
            ->plainTextToken;

        Auth::forgetGuards();

        $this->withHeader('Authorization', "Bearer {$limitedToken}")
            ->postJson("/api/v1/fixed-assets/{$this->asset->id}/maintenance-records", [
                'maintenance_type' => 'service',
                'description' => 'Oil change',
                'performed_on' => '2026-08-01',
            ])->assertStatus(403);
    }

    public function test_maintenance_records_are_scoped_to_their_company(): void
    {
        $otherOwner = User::factory()->create();
        $otherCompany = Company::create([
            'slug' => 'other-'.Str::lower(Str::random(4)),
            'name' => 'Other Ltd',
            'owner_id' => $otherOwner->id,
            'currency' => 'USD',
            'plan' => 'business',
            'account_type' => 'active',
        ]);
        app(CurrentCompany::class)->set($otherCompany);
        $theirAsset = FixedAsset::create([
            'name' => 'Theirs', 'category' => 'equipment', 'acquired_on' => '2026-01-01',
            'cost' => 100000, 'residual_value' => 0, 'method' => 'straight_line',
            'useful_life_months' => 60, 'status' => 'active',
        ]);

        $this->api()->getJson("/api/v1/fixed-assets/{$theirAsset->id}/maintenance-records")->assertStatus(404);
    }
}
