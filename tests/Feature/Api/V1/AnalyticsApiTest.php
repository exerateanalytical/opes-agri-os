<?php

namespace Tests\Feature\Api\V1;

use App\Models\Company;
use App\Models\CropCycle;
use App\Models\Farm;
use App\Models\Field;
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
