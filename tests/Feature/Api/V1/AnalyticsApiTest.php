<?php

namespace Tests\Feature\Api\V1;

use App\Models\Company;
use App\Models\Contact;
use App\Models\CooperativeMember;
use App\Models\CropCycle;
use App\Models\Farm;
use App\Models\Field;
use App\Models\Loan;
use App\Models\PurchaseOrder;
use App\Models\Role;
use App\Models\Season;
use App\Models\User;
use App\Services\ApiTokenIssuer;
use App\Support\CurrentCompany;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AnalyticsApiTest extends TestCase
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
    }

    protected function api()
    {
        $token = app(ApiTokenIssuer::class)->issue($this->owner, $this->company, 'test', ['*'])->plainTextToken;

        return $this->withHeader('Authorization', "Bearer {$token}");
    }

    public function test_it_returns_the_aggregated_dashboard(): void
    {
        $farm = Farm::create(['name' => 'A Farm']);
        $field = Field::create(['farm_id' => $farm->id, 'name' => 'A Field']);
        $season = Season::create(['name' => 'A Season', 'starts_on' => now()->toDateString()]);

        CropCycle::create([
            'field_id' => $field->id, 'season_id' => $season->id, 'status' => 'harvested',
            'planned_yield_qty' => 100, 'actual_yield_qty' => 80,
            'actual_harvest_date' => now()->toDateString(),
        ]);

        $order = PurchaseOrder::create([
            'status' => 'received', 'number' => 'PO-1', 'order_date' => now()->toDateString(), 'total' => 500,
        ]);

        $response = $this->api()->getJson('/api/v1/analytics/dashboard');

        $response->assertOk();
        $response->assertJsonPath('data.crops.enabled', true);
        $response->assertJsonPath('data.crops.harvested_count', 1);
        $this->assertEquals(100.0, $response->json('data.crops.planned_yield'));
        $this->assertEquals(80.0, $response->json('data.crops.actual_yield'));
        $this->assertEquals(80.0, $response->json('data.crops.yield_attainment_pct'));
        $response->assertJsonPath('data.procurement.enabled', true);
        $this->assertEquals(500.0, $response->json('data.procurement.total_spend'));
    }

    public function test_a_date_range_query_param_narrows_the_aggregation(): void
    {
        $farm = Farm::create(['name' => 'A Farm']);
        $field = Field::create(['farm_id' => $farm->id, 'name' => 'A Field']);
        $season = Season::create(['name' => 'A Season', 'starts_on' => now()->toDateString()]);

        CropCycle::create([
            'field_id' => $field->id, 'season_id' => $season->id, 'status' => 'harvested',
            'planned_yield_qty' => 100, 'actual_yield_qty' => 80,
            'actual_harvest_date' => now()->toDateString(),
        ]);
        CropCycle::create([
            'field_id' => $field->id, 'season_id' => $season->id, 'status' => 'harvested',
            'planned_yield_qty' => 20, 'actual_yield_qty' => 15,
            'actual_harvest_date' => now()->subYears(2)->toDateString(),
        ]);

        $response = $this->api()->getJson('/api/v1/analytics/dashboard?from='.now()->subDay()->toDateString().'&to='.now()->addDay()->toDateString());

        $response->assertOk();
        $response->assertJsonPath('data.crops.cycle_count', 1);
        $this->assertEquals(80.0, $response->json('data.crops.actual_yield'));
        $this->assertNotNull($response->json('data.range.from'));
    }

    public function test_an_invalid_date_range_is_rejected(): void
    {
        $response = $this->api()->getJson('/api/v1/analytics/dashboard?from=2026-01-10&to=2026-01-01');

        $response->assertStatus(422);
    }

    public function test_crops_group_by_farm_returns_a_per_farm_breakdown(): void
    {
        $farmA = Farm::create(['name' => 'Farm A']);
        $farmB = Farm::create(['name' => 'Farm B']);
        $fieldA = Field::create(['farm_id' => $farmA->id, 'name' => 'Field A']);
        $fieldB = Field::create(['farm_id' => $farmB->id, 'name' => 'Field B']);
        $season = Season::create(['name' => 'A Season', 'starts_on' => now()->toDateString()]);

        CropCycle::create([
            'field_id' => $fieldA->id, 'season_id' => $season->id, 'status' => 'harvested',
            'planned_yield_qty' => 100, 'actual_yield_qty' => 80,
            'actual_harvest_date' => now()->toDateString(),
        ]);
        CropCycle::create([
            'field_id' => $fieldB->id, 'season_id' => $season->id, 'status' => 'harvested',
            'planned_yield_qty' => 50, 'actual_yield_qty' => 40,
            'actual_harvest_date' => now()->toDateString(),
        ]);

        $response = $this->api()->getJson('/api/v1/analytics/dashboard?crops_group_by=farm');

        $response->assertOk();
        $byFarm = collect($response->json('data.crops.by_farm'))->keyBy('farm_name');

        $this->assertEquals(80.0, $byFarm['Farm A']['actual_yield']);
        $this->assertEquals(40.0, $byFarm['Farm B']['actual_yield']);
    }

    public function test_cooperative_group_by_member_returns_a_per_member_breakdown(): void
    {
        $contact = Contact::create(['type' => 'customer', 'name' => 'Jane Member']);
        $member = CooperativeMember::create([
            'contact_id' => $contact->id, 'membership_number' => 'M-1', 'status' => 'active',
        ]);

        Loan::create([
            'cooperative_member_id' => $member->id, 'principal' => 1000, 'balance' => 800, 'status' => 'active',
        ]);

        $response = $this->api()->getJson('/api/v1/analytics/dashboard?cooperative_group_by=member');

        $response->assertOk();
        $byMember = $response->json('data.cooperative.by_member');

        $this->assertCount(1, $byMember);
        $this->assertEquals('Jane Member', $byMember[0]['member_name']);
        $this->assertEquals(800.0, $byMember[0]['principal_outstanding']);
    }

    public function test_a_disabled_source_module_reports_disabled_rather_than_erroring(): void
    {
        $this->company->forceFill(['modules' => ['crops' => false]])->save();

        $response = $this->api()->getJson('/api/v1/analytics/dashboard');

        $response->assertOk();
        $response->assertJsonPath('data.crops.enabled', false);
        $response->assertJsonCount(1, 'data.crops');
    }

    public function test_it_requires_the_analytics_view_ability(): void
    {
        $token = app(ApiTokenIssuer::class)
            ->issue($this->owner, $this->company, 'no-analytics', ['sales.view'])
            ->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/analytics/dashboard')
            ->assertStatus(403);
    }
}
