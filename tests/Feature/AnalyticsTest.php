<?php

namespace Tests\Feature;

use App\Livewire\Analytics\Dashboard as AnalyticsDashboard;
use App\Models\Company;
use App\Models\CropCycle;
use App\Models\Farm;
use App\Models\Field;
use App\Models\GrantProject;
use App\Models\PurchaseOrder;
use App\Models\Role;
use App\Models\Season;
use App\Models\User;
use App\Support\CurrentCompany;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class AnalyticsTest extends TestCase
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

    public function test_it_aggregates_numbers_from_across_modules(): void
    {
        $farm = Farm::create(['name' => 'A Farm']);
        $field = Field::create(['farm_id' => $farm->id, 'name' => 'A Field']);
        $season = Season::create(['name' => 'A Season', 'starts_on' => now()->toDateString()]);

        CropCycle::create([
            'field_id' => $field->id, 'season_id' => $season->id, 'status' => 'harvested',
            'planned_yield_qty' => 200, 'actual_yield_qty' => 150,
            'actual_harvest_date' => now()->toDateString(),
        ]);
        CropCycle::create([
            'field_id' => $field->id, 'season_id' => $season->id, 'status' => 'planted',
            'planned_yield_qty' => 50,
        ]);

        PurchaseOrder::create([
            'status' => 'received', 'number' => 'PO-1', 'order_date' => now()->toDateString(), 'total' => 300,
        ]);
        PurchaseOrder::create([
            'status' => 'issued', 'number' => 'PO-2', 'order_date' => now()->toDateString(), 'total' => 120,
        ]);

        GrantProject::create([
            'name' => 'Water Access', 'total_amount' => 1000, 'currency' => 'USD', 'status' => 'active',
            'received_amount' => 600, 'spent_amount' => 300,
        ]);

        $component = Livewire::actingAs($this->owner)->test(AnalyticsDashboard::class);

        $summary = $component->viewData('summary');

        $this->assertSame(2, $summary['crops']['cycle_count']);
        $this->assertSame(1, $summary['crops']['harvested_count']);
        $this->assertSame(250.0, $summary['crops']['planned_yield']);
        $this->assertSame(150.0, $summary['crops']['actual_yield']);

        $this->assertSame(420.0, $summary['procurement']['total_spend'] + $summary['procurement']['open_value']);
        $this->assertSame(300.0, $summary['procurement']['total_spend']);
        $this->assertSame(120.0, $summary['procurement']['open_value']);

        $this->assertSame(600.0, $summary['grants']['total_received']);
        $this->assertSame(300.0, $summary['grants']['total_spent']);
        $this->assertSame(50.0, $summary['grants']['utilisation_pct']);
    }

    public function test_a_disabled_analytics_module_hides_the_page(): void
    {
        $this->company->forceFill(['modules' => ['analytics' => false]])->save();

        Livewire::actingAs($this->owner)->test(AnalyticsDashboard::class)->assertForbidden();
    }

    public function test_a_role_without_analytics_view_is_forbidden(): void
    {
        $employee = User::factory()->create();
        $this->joinCompany($this->company, $employee, Role::CASHIER);
        app(CurrentCompany::class)->set($this->company);

        Livewire::actingAs($employee)->test(AnalyticsDashboard::class)->assertForbidden();
    }
}
