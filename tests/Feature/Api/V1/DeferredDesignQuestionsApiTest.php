<?php

namespace Tests\Feature\Api\V1;

use App\Models\Animal;
use App\Models\AnimalBatch;
use App\Models\AnimalBatchAdjustment;
use App\Models\AnimalProductionRecord;
use App\Models\Company;
use App\Models\Contact;
use App\Models\CooperativeMeeting;
use App\Models\CooperativeMember;
use App\Models\CooperativeVote;
use App\Models\CropCycle;
use App\Models\Farm;
use App\Models\Field;
use App\Models\Item;
use App\Models\JournalEntry;
use App\Models\Role;
use App\Models\Season;
use App\Models\User;
use App\Services\ApiTokenIssuer;
use App\Support\Accounting\ChartOfAccounts;
use App\Support\CurrentCompany;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Covers the four questions the roadmap flagged as deliberately deferred and
 * now resolves: harvest/livestock-production ledger valuation, weighted
 * voting, quorum as a hard gate on opening a vote, and a batch's
 * mortality/loss audit trail. See docs/architecture/agri-platform-roadmap.md.
 */
class DeferredDesignQuestionsApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected Company $company;

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
        ChartOfAccounts::seed($this->company);

        $this->token = app(ApiTokenIssuer::class)
            ->issue($this->owner, $this->company, 'test', ['*'])
            ->plainTextToken;
    }

    protected function api()
    {
        return $this->withHeader('Authorization', "Bearer {$this->token}");
    }

    public function test_a_priced_harvest_posts_to_the_ledger(): void
    {
        $farm = Farm::create(['name' => 'Green Valley Farm']);
        $field = Field::create(['farm_id' => $farm->id, 'name' => 'North Plot']);
        $season = Season::create(['name' => '2026 Long Rains', 'starts_on' => '2026-03-01']);
        $item = Item::create(['name' => 'Maize', 'type' => 'product', 'track_stock' => true, 'unit' => 'bag']);
        $cycle = CropCycle::create([
            'field_id' => $field->id, 'season_id' => $season->id, 'item_id' => $item->id,
            'status' => 'planted',
        ]);

        $this->api()->postJson("/api/v1/crop-cycles/{$cycle->id}/harvest", [
            'quantity' => 50,
            'unit_cost' => 200,
        ])->assertOk();

        $entry = JournalEntry::where('source_type', CropCycle::class)->where('source_id', $cycle->id)->first();
        $this->assertNotNull($entry, 'A priced harvest should post a journal entry.');
        $this->assertEquals(10000.0, (float) $entry->lines()->where('debit', '>', 0)->sum('debit'));
    }

    public function test_an_unpriced_harvest_stays_unposted(): void
    {
        $farm = Farm::create(['name' => 'Green Valley Farm']);
        $field = Field::create(['farm_id' => $farm->id, 'name' => 'North Plot']);
        $season = Season::create(['name' => '2026 Long Rains', 'starts_on' => '2026-03-01']);
        $item = Item::create(['name' => 'Maize', 'type' => 'product', 'track_stock' => true, 'unit' => 'bag']);
        $cycle = CropCycle::create([
            'field_id' => $field->id, 'season_id' => $season->id, 'item_id' => $item->id,
            'status' => 'planted',
        ]);

        $this->api()->postJson("/api/v1/crop-cycles/{$cycle->id}/harvest", [
            'quantity' => 50,
        ])->assertOk();

        $this->assertNull(JournalEntry::where('source_type', CropCycle::class)->where('source_id', $cycle->id)->first());
    }

    public function test_a_priced_animal_production_record_posts_to_the_ledger(): void
    {
        $animal = Animal::create(['species' => 'cattle', 'tag_number' => 'C-01', 'status' => 'active']);
        $milk = Item::create(['name' => 'Milk', 'type' => 'product', 'track_stock' => true, 'unit' => 'litre']);

        $response = $this->api()->postJson("/api/v1/animals/{$animal->id}/production-records", [
            'item_id' => $milk->id,
            'quantity' => 10,
            'unit_cost' => 500,
        ]);
        $response->assertSuccessful();

        $recordId = $response->json('data.id');

        $entry = JournalEntry::where('source_type', AnimalProductionRecord::class)->where('source_id', $recordId)->first();
        $this->assertNotNull($entry, 'A priced production record should post a journal entry.');
        $this->assertEquals(5000.0, (float) $entry->lines()->where('debit', '>', 0)->sum('debit'));
    }

    public function test_a_batch_mortality_loss_with_known_unit_cost_posts_to_the_ledger_and_leaves_an_audit_trail(): void
    {
        $batch = AnimalBatch::create([
            'species' => 'broiler', 'initial_count' => 100, 'current_count' => 100,
            'unit_cost' => 15, 'status' => 'active',
        ]);

        $this->api()->postJson("/api/v1/animal-batches/{$batch->id}/adjust-count", [
            'change' => -8,
            'reason' => 'mortality',
        ])->assertOk()->assertJsonPath('data.current_count', 92);

        $trail = $this->api()->getJson("/api/v1/animal-batches/{$batch->id}/adjustments")->assertOk();
        $this->assertCount(1, $trail->json('data'));
        $this->assertSame(-8, $trail->json('data.0.change'));
        $this->assertSame(92, $trail->json('data.0.resulting_count'));
        $this->assertSame('mortality', $trail->json('data.0.reason'));

        $adjustment = AnimalBatchAdjustment::first();
        $entry = JournalEntry::where('source_type', AnimalBatchAdjustment::class)->where('source_id', $adjustment->id)->first();
        $this->assertNotNull($entry, 'A batch loss with a known unit cost should post a journal entry.');
        $this->assertEquals(120.0, (float) $entry->lines()->where('debit', '>', 0)->sum('debit'));
    }

    public function test_a_batch_loss_with_no_known_cost_stays_unposted(): void
    {
        $batch = AnimalBatch::create([
            'species' => 'broiler', 'initial_count' => 100, 'current_count' => 100, 'status' => 'active',
        ]);

        $this->api()->postJson("/api/v1/animal-batches/{$batch->id}/adjust-count", [
            'change' => -5,
        ])->assertOk();

        $adjustment = AnimalBatchAdjustment::first();
        $this->assertNotNull($adjustment);
        $this->assertNull(JournalEntry::where('source_type', AnimalBatchAdjustment::class)->where('source_id', $adjustment->id)->first());
    }

    public function test_weighted_voting_reads_member_weight_instead_of_a_headcount(): void
    {
        $memberA = CooperativeMember::create(['contact_id' => Contact::create(['name' => 'A'])->id, 'status' => 'active', 'vote_weight' => 5]);
        $memberB = CooperativeMember::create(['contact_id' => Contact::create(['name' => 'B'])->id, 'status' => 'active', 'vote_weight' => 1]);

        $response = $this->api()->postJson('/api/v1/cooperative-votes', [
            'title' => 'Approve the capital call',
            'weighted' => true,
        ])->assertCreated();
        $vote = CooperativeVote::findOrFail($response->json('data.id'));

        $this->api()->postJson("/api/v1/cooperative-votes/{$vote->id}/ballots", [
            'cooperative_member_id' => $memberA->id,
            'choice' => 'for',
        ])->assertOk();
        $this->api()->postJson("/api/v1/cooperative-votes/{$vote->id}/ballots", [
            'cooperative_member_id' => $memberB->id,
            'choice' => 'against',
        ])->assertOk();

        $show = $this->api()->getJson("/api/v1/cooperative-votes/{$vote->id}")->assertOk();
        $this->assertEquals(5.0, (float) $show->json('data.tally.for'));
        $this->assertEquals(1.0, (float) $show->json('data.tally.against'));
    }

    public function test_an_unweighted_vote_still_reads_a_plain_headcount(): void
    {
        $memberA = CooperativeMember::create(['contact_id' => Contact::create(['name' => 'A'])->id, 'status' => 'active', 'vote_weight' => 5]);

        $response = $this->api()->postJson('/api/v1/cooperative-votes', [
            'title' => 'Approve the picnic budget',
        ])->assertCreated();
        $vote = CooperativeVote::findOrFail($response->json('data.id'));

        $this->api()->postJson("/api/v1/cooperative-votes/{$vote->id}/ballots", [
            'cooperative_member_id' => $memberA->id,
            'choice' => 'for',
        ])->assertOk();

        $show = $this->api()->getJson("/api/v1/cooperative-votes/{$vote->id}")->assertOk();
        $this->assertEquals(1, $show->json('data.tally.for'));
    }

    public function test_a_vote_cannot_open_against_a_held_meeting_that_missed_quorum(): void
    {
        $meeting = CooperativeMeeting::create([
            'title' => 'AGM', 'scheduled_on' => '2026-09-01', 'quorum_required' => 5, 'status' => 'held',
        ]);

        $this->api()->postJson('/api/v1/cooperative-votes', [
            'title' => 'Approve the new bylaws',
            'cooperative_meeting_id' => $meeting->id,
        ])->assertStatus(409);

        $this->assertSame(0, CooperativeVote::count());
    }

    public function test_a_vote_can_open_against_a_held_meeting_that_met_quorum(): void
    {
        $member = CooperativeMember::create(['contact_id' => Contact::create(['name' => 'A'])->id, 'status' => 'active']);
        $meeting = CooperativeMeeting::create([
            'title' => 'AGM', 'scheduled_on' => '2026-09-01', 'quorum_required' => 1, 'status' => 'held',
        ]);
        $meeting->attendances()->create(['cooperative_member_id' => $member->id]);

        $this->api()->postJson('/api/v1/cooperative-votes', [
            'title' => 'Approve the new bylaws',
            'cooperative_meeting_id' => $meeting->id,
        ])->assertCreated();
    }

    public function test_a_vote_can_open_against_a_scheduled_meeting_not_yet_held(): void
    {
        $meeting = CooperativeMeeting::create([
            'title' => 'AGM', 'scheduled_on' => '2026-09-01', 'quorum_required' => 5, 'status' => 'scheduled',
        ]);

        $this->api()->postJson('/api/v1/cooperative-votes', [
            'title' => 'Approve the new bylaws',
            'cooperative_meeting_id' => $meeting->id,
        ])->assertCreated();
    }
}
