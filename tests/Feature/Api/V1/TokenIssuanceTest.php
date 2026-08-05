<?php

namespace Tests\Feature\Api\V1;

use App\Livewire\Settings\ApiKeys;
use App\Models\Company;
use App\Models\CompanyUserPermission;
use App\Models\Permission;
use App\Models\PersonalAccessToken;
use App\Models\Role;
use App\Models\User;
use App\Support\CurrentCompany;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class TokenIssuanceTest extends TestCase
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

    public function test_an_owner_can_issue_a_token_with_abilities_they_hold(): void
    {
        Livewire::actingAs($this->owner)
            ->test(ApiKeys::class)
            ->set('name', 'Warehouse integration')
            ->set('selectedAbilities', ['sales.view', 'sales.issue'])
            ->call('create')
            ->assertHasNoErrors()
            ->assertSet('newlyIssuedToken', fn ($token) => is_string($token) && $token !== '');

        $token = PersonalAccessToken::query()->where('company_id', $this->company->id)->sole();

        $this->assertSame('Warehouse integration', $token->name);
        $this->assertSame(['sales.view', 'sales.issue'], $token->abilities);
        $this->assertSame($this->owner->id, (int) $token->tokenable_id);
    }

    /**
     * Administrator (not Owner) is the one seeded role that both reaches this
     * page (`settings.update`, granted via its '*' role grant) and can still
     * have a single permission taken away by an explicit override — Owner's
     * blanket grant in User::hasPermissionIn() beats an override outright, so
     * it cannot exercise this path.
     */
    public function test_an_administrator_cannot_grant_an_ability_a_revoke_overrides_away(): void
    {
        $admin = User::factory()->create();
        $this->joinCompany($this->company, $admin, Role::ADMINISTRATOR);

        CompanyUserPermission::create([
            'company_id' => $this->company->id,
            'user_id' => $admin->id,
            'permission_id' => Permission::where('slug', 'payroll.run')->firstOrFail()->id,
            'granted' => false,
        ]);

        Livewire::actingAs($admin)
            ->test(ApiKeys::class)
            ->set('name', 'Admin key')
            ->set('selectedAbilities', ['payroll.run'])
            ->call('create')
            ->assertHasErrors('selectedAbilities.*');

        $this->assertSame(0, PersonalAccessToken::query()->where('company_id', $this->company->id)->count());
    }

    public function test_a_user_without_settings_permission_cannot_open_the_page(): void
    {
        $cashier = User::factory()->create();
        $this->joinCompany($this->company, $cashier, Role::CASHIER);

        Livewire::actingAs($cashier)
            ->test(ApiKeys::class)
            ->assertForbidden();
    }

    public function test_a_token_can_be_revoked(): void
    {
        Livewire::actingAs($this->owner)
            ->test(ApiKeys::class)
            ->set('name', 'To revoke')
            ->set('selectedAbilities', ['sales.view'])
            ->call('create');

        $token = PersonalAccessToken::query()->where('company_id', $this->company->id)->sole();

        Livewire::actingAs($this->owner)
            ->test(ApiKeys::class)
            ->call('revoke', $token->id);

        $this->assertSame(0, PersonalAccessToken::query()->where('id', $token->id)->count());
    }

    public function test_a_token_cannot_be_revoked_from_another_company(): void
    {
        Livewire::actingAs($this->owner)
            ->test(ApiKeys::class)
            ->set('name', 'Belongs to Acme')
            ->set('selectedAbilities', ['sales.view'])
            ->call('create');

        $token = PersonalAccessToken::query()->where('company_id', $this->company->id)->sole();

        $otherOwner = User::factory()->create();
        $otherCompany = Company::create([
            'slug' => 'other-'.Str::lower(Str::random(4)),
            'name' => 'Other Ltd',
            'owner_id' => $otherOwner->id,
            'currency' => 'USD',
        ]);
        $this->joinCompany($otherCompany, $otherOwner, Role::OWNER);
        app(CurrentCompany::class)->set($otherCompany);

        Livewire::actingAs($otherOwner)
            ->test(ApiKeys::class)
            ->call('revoke', $token->id);

        $this->assertSame(1, PersonalAccessToken::query()->where('id', $token->id)->count());
    }
}
