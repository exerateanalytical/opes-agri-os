<?php

namespace Tests\Feature\Api\V1;

use App\Models\Company;
use App\Models\Contact;
use App\Models\CooperativeMember;
use App\Models\Role;
use App\Models\User;
use App\Services\ApiTokenIssuer;
use App\Support\CurrentCompany;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Tests\TestCase;

class CooperativeMembersApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected Company $company;

    protected Contact $contact;

    protected string $token;

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

        $this->token = app(ApiTokenIssuer::class)
            ->issue($this->owner, $this->company, 'test', ['*'])
            ->plainTextToken;
    }

    protected function api()
    {
        return $this->withHeader('Authorization', "Bearer {$this->token}");
    }

    public function test_it_creates_a_member_in_active_status(): void
    {
        $response = $this->api()->postJson('/api/v1/cooperative-members', [
            'contact_id' => $this->contact->id,
            'membership_number' => 'MEM-001',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.balance', '0.00');
    }

    public function test_a_contact_cannot_be_a_member_twice(): void
    {
        app(CurrentCompany::class)->set($this->company);
        CooperativeMember::create(['contact_id' => $this->contact->id, 'status' => 'active']);

        $this->api()->postJson('/api/v1/cooperative-members', [
            'contact_id' => $this->contact->id,
        ])->assertStatus(422);
    }

    public function test_recording_a_contribution_updates_the_balance(): void
    {
        app(CurrentCompany::class)->set($this->company);
        $member = CooperativeMember::create(['contact_id' => $this->contact->id, 'status' => 'active']);

        $response = $this->api()->postJson("/api/v1/cooperative-members/{$member->id}/contributions", [
            'type' => 'share_capital',
            'amount' => 5000,
            'contributed_on' => '2026-08-01',
        ]);

        $response->assertCreated()->assertJsonPath('data.amount', '5000.00');
        $this->assertSame('5000.00', $member->fresh()->balance);

        $this->api()->postJson("/api/v1/cooperative-members/{$member->id}/contributions", [
            'type' => 'savings',
            'amount' => 1500,
            'contributed_on' => '2026-08-02',
        ])->assertCreated();

        $this->assertSame('6500.00', $member->fresh()->balance);
    }

    public function test_a_token_without_the_record_contribution_ability_is_refused(): void
    {
        app(CurrentCompany::class)->set($this->company);
        $member = CooperativeMember::create(['contact_id' => $this->contact->id, 'status' => 'active']);

        $viewOnlyToken = app(ApiTokenIssuer::class)
            ->issue($this->owner, $this->company, 'view-only', ['cooperative.view'])
            ->plainTextToken;

        Auth::forgetGuards();

        $this->withHeader('Authorization', "Bearer {$viewOnlyToken}")
            ->postJson("/api/v1/cooperative-members/{$member->id}/contributions", [
                'type' => 'savings',
                'amount' => 100,
                'contributed_on' => '2026-08-01',
            ])->assertStatus(403);
    }

    public function test_members_are_scoped_to_their_company(): void
    {
        $otherOwner = User::factory()->create();
        $otherCompany = Company::create([
            'slug' => 'other-'.Str::lower(Str::random(4)),
            'name' => 'Other Ltd',
            'owner_id' => $otherOwner->id,
            'currency' => 'USD',
        ]);
        app(CurrentCompany::class)->set($otherCompany);
        $theirContact = Contact::create(['name' => 'Theirs']);
        $theirMember = CooperativeMember::create(['contact_id' => $theirContact->id, 'status' => 'active']);

        $this->api()->getJson("/api/v1/cooperative-members/{$theirMember->id}")->assertStatus(404);
    }
}
