<?php

namespace Tests\Feature;

use App\Livewire\Analytics\Dashboard as AnalyticsDashboard;
use App\Models\Company;
use App\Models\Contact;
use App\Models\CooperativeMember;
use App\Models\CropCycle;
use App\Models\Farm;
use App\Models\Field;
use App\Models\GrantProject;
use App\Models\Loan;
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

    public function test_a_custom_date_range_narrows_the_aggregation(): void
    {
        $farm = Farm::create(['name' => 'A Farm']);
        $field = Field::create(['farm_id' => $farm->id, 'name' => 'A Field']);
        $season = Season::create(['name' => 'A Season', 'starts_on' => now()->toDateString()]);

        CropCycle::create([
            'field_id' => $field->id, 'season_id' => $season->id, 'status' => 'harvested',
            'planned_yield_qty' => 100, 'actual_yield_qty' => 90,
            'actual_harvest_date' => now()->toDateString(),
        ]);
        CropCycle::create([
            'field_id' => $field->id, 'season_id' => $season->id, 'status' => 'harvested',
            'planned_yield_qty' => 40, 'actual_yield_qty' => 30,
            'actual_harvest_date' => now()->subYear()->toDateString(),
        ]);

        $component = Livewire::actingAs($this->owner)->test(AnalyticsDashboard::class)
            ->set('from', now()->subDays(1)->toDateString())
            ->set('to', now()->addDays(1)->toDateString());

        $summary = $component->viewData('summary');

        $this->assertSame(1, $summary['crops']['cycle_count']);
        $this->assertSame(90.0, $summary['crops']['actual_yield']);
        $this->assertNotNull($summary['range']['from']);
    }

    public function test_drilldown_lists_the_underlying_records_for_a_section(): void
    {
        $farm = Farm::create(['name' => 'A Farm']);
        $field = Field::create(['farm_id' => $farm->id, 'name' => 'A Field']);
        $season = Season::create(['name' => 'A Season', 'starts_on' => now()->toDateString()]);

        $cycle = CropCycle::create([
            'field_id' => $field->id, 'season_id' => $season->id, 'status' => 'harvested',
            'planned_yield_qty' => 100, 'actual_yield_qty' => 90,
            'actual_harvest_date' => now()->toDateString(),
        ]);

        $component = Livewire::actingAs($this->owner)->test(AnalyticsDashboard::class)
            ->call('toggleDrilldown', 'crops');

        $rows = $component->viewData('drilldownRows');

        $this->assertCount(1, $rows);
        $this->assertSame($cycle->id, $rows->first()->id);

        // Toggling again collapses it.
        $component->call('toggleDrilldown', 'crops');
        $this->assertCount(0, $component->viewData('drilldownRows'));
    }

    public function test_crops_can_be_broken_down_by_farm(): void
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

        $component = Livewire::actingAs($this->owner)->test(AnalyticsDashboard::class)
            ->set('cropsGroupBy', 'farm');

        $byFarm = collect($component->viewData('summary')['crops']['by_farm'])->keyBy('farm_name');

        $this->assertSame(80.0, $byFarm['Farm A']['actual_yield']);
        $this->assertSame(40.0, $byFarm['Farm B']['actual_yield']);
    }

    public function test_cooperative_can_be_broken_down_by_member(): void
    {
        $contact = Contact::create(['type' => 'customer', 'name' => 'Jane Member']);
        $member = CooperativeMember::create([
            'contact_id' => $contact->id, 'membership_number' => 'M-1', 'status' => 'active',
        ]);

        Loan::create([
            'cooperative_member_id' => $member->id, 'principal' => 1000, 'balance' => 800, 'status' => 'active',
        ]);

        $component = Livewire::actingAs($this->owner)->test(AnalyticsDashboard::class)
            ->set('cooperativeGroupBy', 'member');

        $byMember = $component->viewData('summary')['cooperative']['by_member'];

        $this->assertCount(1, $byMember);
        $this->assertSame('Jane Member', $byMember[0]['member_name']);
        $this->assertSame(800.0, $byMember[0]['principal_outstanding']);
    }

    public function test_csv_export_streams_the_current_view(): void
    {
        PurchaseOrder::create([
            'status' => 'received', 'number' => 'PO-1', 'order_date' => now()->toDateString(), 'total' => 300,
        ]);

        $response = Livewire::actingAs($this->owner)->test(AnalyticsDashboard::class)
            ->call('exportCsv');

        $response->assertFileDownloaded();

        $downloadEffect = data_get($response->effects, 'download');
        $csv = base64_decode(data_get($downloadEffect, 'content'));

        $this->assertStringContainsString('Section,Metric,Value', $csv);
        $this->assertStringContainsString('procurement,total_spend,300', $csv);
        $this->assertSame('text/csv', data_get($downloadEffect, 'contentType'));
    }

    public function test_a_role_without_analytics_export_cannot_export(): void
    {
        $employee = User::factory()->create();
        $this->joinCompany($this->company, $employee, Role::SALES_OFFICER);
        app(CurrentCompany::class)->set($this->company);

        Livewire::actingAs($employee)->test(AnalyticsDashboard::class)
            ->call('exportCsv')
            ->assertForbidden();
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
