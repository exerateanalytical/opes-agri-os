<?php

namespace Tests\Feature;

use App\Livewire\Cooperative\Index as CooperativeIndex;
use App\Livewire\Cooperative\RecordContribution;
use App\Models\Company;
use App\Models\Contact;
use App\Models\CooperativeMeeting;
use App\Models\CooperativeMember;
use App\Models\CooperativeVote;
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

    public function test_it_creates_a_meeting_and_marks_attendance(): void
    {
        $member = CooperativeMember::create(['contact_id' => $this->contact->id, 'status' => 'active']);

        $component = Livewire::actingAs($this->owner)
            ->test(CooperativeIndex::class)
            ->call('startAddingMeeting')
            ->set('meetingTitle', 'AGM')
            ->set('meetingScheduledOn', '2026-09-01')
            ->set('meetingQuorumRequired', '1')
            ->call('saveMeeting')
            ->assertHasNoErrors();

        $meeting = CooperativeMeeting::query()->where('company_id', $this->company->id)->sole();
        $this->assertFalse($meeting->quorumMet());

        $component->call('openAttendance', $meeting->id)
            ->set('attendanceMemberId', $member->id)
            ->call('saveAttendance')
            ->assertHasNoErrors();

        $this->assertTrue($meeting->fresh()->quorumMet());
    }

    public function test_it_opens_a_vote_and_casts_a_ballot(): void
    {
        $member = CooperativeMember::create(['contact_id' => $this->contact->id, 'status' => 'active']);

        $component = Livewire::actingAs($this->owner)
            ->test(CooperativeIndex::class)
            ->call('startAddingVote')
            ->set('voteTitle', 'Approve the new bylaws')
            ->call('saveVote')
            ->assertHasNoErrors();

        $vote = CooperativeVote::query()->where('company_id', $this->company->id)->sole();

        $component->call('openBallot', $vote->id)
            ->set('ballotMemberId', $member->id)
            ->set('ballotChoice', 'for')
            ->call('saveBallot')
            ->assertHasNoErrors();

        $this->assertSame(1, $vote->fresh()->tally()['for']);

        $component->call('closeVote', $vote->id);
        $this->assertSame('closed', $vote->fresh()->status);
    }
}
