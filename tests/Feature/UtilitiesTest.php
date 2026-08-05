<?php

namespace Tests\Feature;

use App\Livewire\Utilities\Index as UtilitiesIndex;
use App\Livewire\Utilities\RecordReading;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Models\UtilityAccount;
use App\Support\CurrentCompany;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class UtilitiesTest extends TestCase
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

    public function test_it_creates_a_utility_account(): void
    {
        Livewire::actingAs($this->owner)
            ->test(UtilitiesIndex::class)
            ->call('startAdding')
            ->set('utilityType', 'electricity')
            ->set('providerName', 'ENEO')
            ->call('save')
            ->assertHasNoErrors();

        $account = UtilityAccount::query()->where('company_id', $this->company->id)->sole();
        $this->assertSame('active', $account->status);
    }

    public function test_recording_a_reading(): void
    {
        $account = UtilityAccount::create(['utility_type' => 'water', 'status' => 'active']);

        Livewire::actingAs($this->owner)
            ->test(RecordReading::class)
            ->call('open', $account->id)
            ->set('consumption', '45')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(1, $account->readings()->count());
    }

    public function test_a_disabled_utilities_module_hides_the_page(): void
    {
        $this->company->forceFill(['modules' => ['utilities' => false]])->save();

        Livewire::actingAs($this->owner)->test(UtilitiesIndex::class)->assertForbidden();
    }
}
