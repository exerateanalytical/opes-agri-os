<?php

namespace Tests\Feature;

use App\Livewire\Partners\Index as PartnersIndex;
use App\Livewire\Partners\RecordInteraction;
use App\Models\Company;
use App\Models\Partner;
use App\Models\Role;
use App\Models\User;
use App\Support\CurrentCompany;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class PartnerCrmTest extends TestCase
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

    public function test_it_creates_a_partner(): void
    {
        Livewire::actingAs($this->owner)
            ->test(PartnersIndex::class)
            ->call('startAdding')
            ->set('name', 'Green Future NGO')
            ->set('type', 'ngo')
            ->call('save')
            ->assertHasNoErrors();

        $partner = Partner::query()->where('company_id', $this->company->id)->sole();
        $this->assertSame('active', $partner->status);
        $this->assertSame('Green Future NGO', $partner->name);
    }

    public function test_logging_an_interaction(): void
    {
        $partner = Partner::create(['name' => 'World Aid', 'type' => 'donor', 'status' => 'active']);

        Livewire::actingAs($this->owner)
            ->test(RecordInteraction::class)
            ->call('open', $partner->id)
            ->set('type', 'call')
            ->set('summary', 'Discussed Q3 funding.')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(1, $partner->interactions()->count());
    }

    public function test_a_disabled_partner_crm_module_hides_the_page(): void
    {
        $this->company->forceFill(['modules' => ['partner_crm' => false]])->save();

        Livewire::actingAs($this->owner)->test(PartnersIndex::class)->assertForbidden();
    }
}
