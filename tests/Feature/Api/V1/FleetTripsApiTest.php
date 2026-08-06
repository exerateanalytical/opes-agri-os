<?php

namespace Tests\Feature\Api\V1;

use App\Models\Company;
use App\Models\FixedAsset;
use App\Models\FleetTrip;
use App\Models\Role;
use App\Models\User;
use App\Services\ApiTokenIssuer;
use App\Support\Accounting\ChartOfAccounts;
use App\Support\CurrentCompany;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class FleetTripsApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected Company $company;

    protected FixedAsset $van;

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

        $this->van = FixedAsset::create([
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

    public function test_it_logs_a_trip_and_computes_distance(): void
    {
        $response = $this->api()->postJson("/api/v1/fixed-assets/{$this->van->id}/trips", [
            'driver_name' => 'Jean',
            'purpose' => 'Delivery run',
            'started_on' => '2026-08-01',
            'start_odometer' => 10000,
            'end_odometer' => 10120.5,
            'fuel_cost' => 25000,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.driver_name', 'Jean')
            ->assertJsonPath('data.distance', 120.5);

        $this->api()->getJson("/api/v1/fixed-assets/{$this->van->id}/trips")
            ->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_the_end_odometer_cannot_be_less_than_the_start(): void
    {
        $this->api()->postJson("/api/v1/fixed-assets/{$this->van->id}/trips", [
            'started_on' => '2026-08-01',
            'start_odometer' => 10000,
            'end_odometer' => 9000,
        ])->assertStatus(422);
    }

    public function test_the_model_rejects_a_decreasing_odometer_even_bypassing_the_form_request(): void
    {
        $this->expectException(ValidationException::class);

        FleetTrip::create([
            'fixed_asset_id' => $this->van->id,
            'started_on' => '2026-08-01',
            'start_odometer' => 10000,
            'end_odometer' => 9000,
        ]);
    }

    public function test_a_trip_cannot_be_logged_against_a_disposed_asset(): void
    {
        $this->van->forceFill(['status' => 'disposed', 'disposed_on' => '2026-07-01'])->save();

        $this->api()->postJson("/api/v1/fixed-assets/{$this->van->id}/trips", [
            'started_on' => '2026-08-01',
        ])->assertStatus(422);
    }

    public function test_a_token_without_the_record_trip_ability_is_refused(): void
    {
        $limitedToken = app(ApiTokenIssuer::class)
            ->issue($this->owner, $this->company, 'view-only', ['assets.view'])
            ->plainTextToken;

        Auth::forgetGuards();

        $this->withHeader('Authorization', "Bearer {$limitedToken}")
            ->postJson("/api/v1/fixed-assets/{$this->van->id}/trips", ['started_on' => '2026-08-01'])
            ->assertStatus(403);
    }

    public function test_trips_are_scoped_to_their_company(): void
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
        $theirVan = FixedAsset::create([
            'name' => 'Theirs', 'category' => 'vehicles', 'acquired_on' => '2026-01-01',
            'cost' => 100000, 'residual_value' => 0, 'method' => 'straight_line',
            'useful_life_months' => 60, 'status' => 'active',
        ]);

        $this->api()->getJson("/api/v1/fixed-assets/{$theirVan->id}/trips")->assertStatus(404);
    }
}
