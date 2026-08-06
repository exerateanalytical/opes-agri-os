<?php

namespace Tests\Feature\Api\V1;

use App\Models\Company;
use App\Models\Contact;
use App\Models\CooperativeMeeting;
use App\Models\CooperativeMember;
use App\Models\CooperativeVote;
use App\Models\Role;
use App\Models\User;
use App\Services\ApiTokenIssuer;
use App\Support\CurrentCompany;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Tests\TestCase;

class CooperativeGovernanceApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected Company $company;

    protected CooperativeMember $memberA;

    protected CooperativeMember $memberB;

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

        $this->memberA = CooperativeMember::create(['contact_id' => Contact::create(['name' => 'A'])->id, 'status' => 'active']);
        $this->memberB = CooperativeMember::create(['contact_id' => Contact::create(['name' => 'B'])->id, 'status' => 'active']);

        $this->token = app(ApiTokenIssuer::class)
            ->issue($this->owner, $this->company, 'test', ['*'])
            ->plainTextToken;
    }

    protected function api()
    {
        return $this->withHeader('Authorization', "Bearer {$this->token}");
    }

    public function test_it_creates_a_meeting(): void
    {
        $response = $this->api()->postJson('/api/v1/cooperative-meetings', [
            'title' => 'Annual General Meeting',
            'scheduled_on' => '2026-09-01',
            'quorum_required' => 2,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'scheduled')
            ->assertJsonPath('data.quorum_met', false);
    }

    public function test_attendance_reaches_quorum(): void
    {
        app(CurrentCompany::class)->set($this->company);
        $meeting = CooperativeMeeting::create([
            'title' => 'AGM', 'scheduled_on' => '2026-09-01', 'quorum_required' => 2, 'status' => 'scheduled',
        ]);

        $this->api()->postJson("/api/v1/cooperative-meetings/{$meeting->id}/attendance", [
            'cooperative_member_id' => $this->memberA->id,
        ])->assertOk()->assertJsonPath('data.quorum_met', false);

        $this->api()->postJson("/api/v1/cooperative-meetings/{$meeting->id}/attendance", [
            'cooperative_member_id' => $this->memberB->id,
        ])->assertOk()->assertJsonPath('data.quorum_met', true)->assertJsonPath('data.attendance_count', 2);
    }

    public function test_attendance_is_idempotent_per_member(): void
    {
        app(CurrentCompany::class)->set($this->company);
        $meeting = CooperativeMeeting::create([
            'title' => 'AGM', 'scheduled_on' => '2026-09-01', 'quorum_required' => 1, 'status' => 'scheduled',
        ]);

        $this->api()->postJson("/api/v1/cooperative-meetings/{$meeting->id}/attendance", [
            'cooperative_member_id' => $this->memberA->id,
        ])->assertOk();
        $this->api()->postJson("/api/v1/cooperative-meetings/{$meeting->id}/attendance", [
            'cooperative_member_id' => $this->memberA->id,
        ])->assertOk()->assertJsonPath('data.attendance_count', 1);
    }

    public function test_it_opens_a_vote_and_tallies_ballots(): void
    {
        $response = $this->api()->postJson('/api/v1/cooperative-votes', [
            'title' => 'Approve the new bylaws',
        ]);
        $response->assertCreated()->assertJsonPath('data.status', 'open');
        $vote = CooperativeVote::findOrFail($response->json('data.id'));

        $this->api()->postJson("/api/v1/cooperative-votes/{$vote->id}/ballots", [
            'cooperative_member_id' => $this->memberA->id,
            'choice' => 'for',
        ])->assertOk();

        $this->api()->postJson("/api/v1/cooperative-votes/{$vote->id}/ballots", [
            'cooperative_member_id' => $this->memberB->id,
            'choice' => 'against',
        ])->assertOk()->assertJsonPath('data.tally.for', 1)->assertJsonPath('data.tally.against', 1)
            ->assertJsonPath('data.tally.total', 2);
    }

    public function test_a_member_cannot_vote_twice(): void
    {
        app(CurrentCompany::class)->set($this->company);
        $vote = CooperativeVote::create(['title' => 'Motion', 'status' => 'open', 'opened_on' => now()->toDateString()]);

        $this->api()->postJson("/api/v1/cooperative-votes/{$vote->id}/ballots", [
            'cooperative_member_id' => $this->memberA->id,
            'choice' => 'for',
        ])->assertOk();

        $this->api()->postJson("/api/v1/cooperative-votes/{$vote->id}/ballots", [
            'cooperative_member_id' => $this->memberA->id,
            'choice' => 'against',
        ])->assertOk()->assertJsonPath('data.tally.for', 1)->assertJsonPath('data.tally.against', 0);
    }

    public function test_closing_a_vote_refuses_further_ballots(): void
    {
        app(CurrentCompany::class)->set($this->company);
        $vote = CooperativeVote::create(['title' => 'Motion', 'status' => 'open', 'opened_on' => now()->toDateString()]);

        $this->api()->postJson("/api/v1/cooperative-votes/{$vote->id}/close")->assertOk()->assertJsonPath('data.status', 'closed');

        $this->api()->postJson("/api/v1/cooperative-votes/{$vote->id}/ballots", [
            'cooperative_member_id' => $this->memberA->id,
            'choice' => 'for',
        ])->assertStatus(409);
    }

    public function test_a_token_without_the_cast_vote_ability_is_refused(): void
    {
        app(CurrentCompany::class)->set($this->company);
        $vote = CooperativeVote::create(['title' => 'Motion', 'status' => 'open', 'opened_on' => now()->toDateString()]);

        $limitedToken = app(ApiTokenIssuer::class)
            ->issue($this->owner, $this->company, 'limited', ['cooperative.view'])
            ->plainTextToken;

        Auth::forgetGuards();

        $this->withHeader('Authorization', "Bearer {$limitedToken}")
            ->postJson("/api/v1/cooperative-votes/{$vote->id}/ballots", [
                'cooperative_member_id' => $this->memberA->id,
                'choice' => 'for',
            ])->assertStatus(403);
    }

    public function test_a_vote_cannot_open_against_a_scheduled_meeting_even_with_quorum_required(): void
    {
        app(CurrentCompany::class)->set($this->company);
        $meeting = CooperativeMeeting::create([
            'title' => 'AGM', 'scheduled_on' => '2026-09-01', 'quorum_required' => 2, 'status' => 'scheduled',
        ]);

        // No attendance at all recorded — a scheduled meeting must not be
        // usable to bypass the quorum gate just by never being marked held.
        $this->api()->postJson('/api/v1/cooperative-votes', [
            'cooperative_meeting_id' => $meeting->id,
            'title' => 'Approve the budget',
        ])->assertStatus(409);

        $this->assertSame(0, CooperativeVote::query()->count());
    }

    public function test_a_vote_cannot_open_against_a_scheduled_meeting_even_with_insufficient_attendance(): void
    {
        app(CurrentCompany::class)->set($this->company);
        $meeting = CooperativeMeeting::create([
            'title' => 'AGM', 'scheduled_on' => '2026-09-01', 'quorum_required' => 2, 'status' => 'scheduled',
        ]);

        $meeting->attendances()->create(['cooperative_member_id' => $this->memberA->id]);

        $this->api()->postJson('/api/v1/cooperative-votes', [
            'cooperative_meeting_id' => $meeting->id,
            'title' => 'Approve the budget',
        ])->assertStatus(409);

        $this->assertSame(0, CooperativeVote::query()->count());
    }

    public function test_a_vote_cannot_open_against_a_held_meeting_that_did_not_reach_quorum(): void
    {
        app(CurrentCompany::class)->set($this->company);
        $meeting = CooperativeMeeting::create([
            'title' => 'AGM', 'scheduled_on' => '2026-09-01', 'quorum_required' => 2, 'status' => 'held',
        ]);
        $meeting->attendances()->create(['cooperative_member_id' => $this->memberA->id]);

        $this->api()->postJson('/api/v1/cooperative-votes', [
            'cooperative_meeting_id' => $meeting->id,
            'title' => 'Approve the budget',
        ])->assertStatus(409);
    }

    public function test_a_vote_opens_against_a_held_meeting_that_reached_quorum(): void
    {
        app(CurrentCompany::class)->set($this->company);
        $meeting = CooperativeMeeting::create([
            'title' => 'AGM', 'scheduled_on' => '2026-09-01', 'quorum_required' => 2, 'status' => 'held',
        ]);
        $meeting->attendances()->create(['cooperative_member_id' => $this->memberA->id]);
        $meeting->attendances()->create(['cooperative_member_id' => $this->memberB->id]);

        $this->api()->postJson('/api/v1/cooperative-votes', [
            'cooperative_meeting_id' => $meeting->id,
            'title' => 'Approve the budget',
        ])->assertCreated();
    }

    public function test_a_weighted_tally_uses_the_weight_snapshotted_at_cast_time(): void
    {
        app(CurrentCompany::class)->set($this->company);
        $this->memberA->update(['vote_weight' => 3]);
        $this->memberB->update(['vote_weight' => 1]);

        $vote = CooperativeVote::create([
            'title' => 'Capital call', 'status' => 'open', 'opened_on' => now()->toDateString(), 'weighted' => true,
        ]);

        $this->api()->postJson("/api/v1/cooperative-votes/{$vote->id}/ballots", [
            'cooperative_member_id' => $this->memberA->id,
            'choice' => 'for',
        ])->assertOk();
        $this->api()->postJson("/api/v1/cooperative-votes/{$vote->id}/ballots", [
            'cooperative_member_id' => $this->memberB->id,
            'choice' => 'against',
        ])->assertOk();

        $originalTally = $vote->fresh()->tally();
        $this->assertSame(3.0, $originalTally['for']);
        $this->assertSame(1.0, $originalTally['against']);

        // Change the member's weight after casting — and after closing —
        // and the tally must not move: it reads what was snapshotted, not
        // the live column.
        $this->api()->postJson("/api/v1/cooperative-votes/{$vote->id}/close")->assertOk();
        $this->memberA->update(['vote_weight' => 50]);

        $newTally = $vote->fresh()->tally();
        $this->assertSame($originalTally, $newTally);
    }

    public function test_casting_a_vote_requires_attendance_when_the_meeting_tracks_quorum(): void
    {
        app(CurrentCompany::class)->set($this->company);
        $meeting = CooperativeMeeting::create([
            'title' => 'AGM', 'scheduled_on' => '2026-09-01', 'quorum_required' => 1, 'status' => 'held',
        ]);
        $meeting->attendances()->create(['cooperative_member_id' => $this->memberA->id]);

        $vote = CooperativeVote::create([
            'cooperative_meeting_id' => $meeting->id, 'title' => 'Motion', 'status' => 'open', 'opened_on' => now()->toDateString(),
        ]);

        // memberB never attended.
        $this->api()->postJson("/api/v1/cooperative-votes/{$vote->id}/ballots", [
            'cooperative_member_id' => $this->memberB->id,
            'choice' => 'for',
        ])->assertStatus(409);

        $this->api()->postJson("/api/v1/cooperative-votes/{$vote->id}/ballots", [
            'cooperative_member_id' => $this->memberA->id,
            'choice' => 'for',
        ])->assertOk();
    }

    public function test_meetings_and_votes_are_scoped_to_their_company(): void
    {
        $otherOwner = User::factory()->create();
        $otherCompany = Company::create([
            'slug' => 'other-'.Str::lower(Str::random(4)),
            'name' => 'Other Ltd',
            'owner_id' => $otherOwner->id,
            'currency' => 'USD',
        ]);
        app(CurrentCompany::class)->set($otherCompany);
        $theirMeeting = CooperativeMeeting::create(['title' => 'Theirs', 'scheduled_on' => now()->toDateString(), 'status' => 'scheduled']);

        $this->api()->getJson("/api/v1/cooperative-meetings/{$theirMeeting->id}")->assertStatus(404);
    }
}
