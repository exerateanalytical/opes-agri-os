<?php

namespace Tests\Feature;

use App\Livewire\Cooperative\Index as CooperativeIndex;
use App\Livewire\Cooperative\RecordContribution;
use App\Models\Company;
use App\Models\Contact;
use App\Models\CooperativeMember;
use App\Models\Role;
use App\Models\User;
use App\Support\CurrentCompany;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class CooperativeTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected Company $company;

    protected Contact $contact;

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

        $this->contact = Contact::create(['name' => 'Jane Farmer']);
    }

    public function test_it_creates_a_member(): void
    {
        Livewire::actingAs($this->owner)
            ->test(CooperativeIndex::class)
            ->call('startAdding')
            ->set('contactId', $this->contact->id)
            ->set('membershipNumber', 'MEM-001')
            ->call('save')
            ->assertHasNoErrors();

        $member = CooperativeMember::query()->where('company_id', $this->company->id)->sole();
        $this->assertSame('active', $member->status);
    }

    public function test_recording_a_contribution_updates_the_balance(): void
    {
        $member = CooperativeMember::create(['contact_id' => $this->contact->id, 'status' => 'active']);

        Livewire::actingAs($this->owner)
            ->test(RecordContribution::class)
            ->call('open', $member->id)
            ->set('amount', '3000')
            ->set('contributedOn', '2026-08-01')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('3000.00', $member->fresh()->balance);
    }

    public function test_a_disabled_cooperative_module_hides_the_page(): void
    {
        $this->company->forceFill(['modules' => ['cooperative' => false]])->save();

        Livewire::actingAs($this->owner)->test(CooperativeIndex::class)->assertForbidden();
    }
}
