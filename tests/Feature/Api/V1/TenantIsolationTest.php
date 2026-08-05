<?php

namespace Tests\Feature\Api\V1;

use App\Models\Company;
use App\Models\Contact;
use App\Models\Role;
use App\Models\User;
use App\Services\ApiTokenIssuer;
use App\Support\CurrentCompany;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * A token bound to Company A must never see or reach Company B's records,
 * even by guessing an id — the response should read exactly like "this
 * doesn't exist", not like a wall it can be told apart from a real 403.
 */
class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_token_cannot_read_another_companys_contact_by_id(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $ownerA = User::factory()->create();
        $companyA = Company::create([
            'slug' => 'a-'.Str::lower(Str::random(4)),
            'name' => 'Company A',
            'owner_id' => $ownerA->id,
            'currency' => 'USD',
        ]);
        $this->joinCompany($companyA, $ownerA, Role::OWNER);

        $ownerB = User::factory()->create();
        $companyB = Company::create([
            'slug' => 'b-'.Str::lower(Str::random(4)),
            'name' => 'Company B',
            'owner_id' => $ownerB->id,
            'currency' => 'USD',
        ]);
        $this->joinCompany($companyB, $ownerB, Role::OWNER);

        app(CurrentCompany::class)->set($companyB);
        $bContact = Contact::create(['name' => 'B Customer']);

        $tokenA = app(ApiTokenIssuer::class)->issue($ownerA, $companyA, 'a', ['*'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$tokenA}")
            ->getJson("/api/v1/contacts/{$bContact->id}")
            ->assertStatus(404);
    }

    public function test_a_tokens_index_never_lists_another_companys_records(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $ownerA = User::factory()->create();
        $companyA = Company::create([
            'slug' => 'a-'.Str::lower(Str::random(4)),
            'name' => 'Company A',
            'owner_id' => $ownerA->id,
            'currency' => 'USD',
        ]);
        $this->joinCompany($companyA, $ownerA, Role::OWNER);

        $ownerB = User::factory()->create();
        $companyB = Company::create([
            'slug' => 'b-'.Str::lower(Str::random(4)),
            'name' => 'Company B',
            'owner_id' => $ownerB->id,
            'currency' => 'USD',
        ]);
        $this->joinCompany($companyB, $ownerB, Role::OWNER);

        app(CurrentCompany::class)->set($companyA);
        Contact::create(['name' => 'A Customer']);
        app(CurrentCompany::class)->set($companyB);
        Contact::create(['name' => 'B Customer']);

        $tokenA = app(ApiTokenIssuer::class)->issue($ownerA, $companyA, 'a', ['*'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$tokenA}")->getJson('/api/v1/contacts');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name')->all();
        $this->assertSame(['A Customer'], $names);
    }

    public function test_a_token_cannot_update_another_companys_contact(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $ownerA = User::factory()->create();
        $companyA = Company::create([
            'slug' => 'a-'.Str::lower(Str::random(4)),
            'name' => 'Company A',
            'owner_id' => $ownerA->id,
            'currency' => 'USD',
        ]);
        $this->joinCompany($companyA, $ownerA, Role::OWNER);

        $ownerB = User::factory()->create();
        $companyB = Company::create([
            'slug' => 'b-'.Str::lower(Str::random(4)),
            'name' => 'Company B',
            'owner_id' => $ownerB->id,
            'currency' => 'USD',
        ]);
        $this->joinCompany($companyB, $ownerB, Role::OWNER);

        app(CurrentCompany::class)->set($companyB);
        $bContact = Contact::create(['name' => 'B Customer']);

        $tokenA = app(ApiTokenIssuer::class)->issue($ownerA, $companyA, 'a', ['*'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$tokenA}")
            ->patchJson("/api/v1/contacts/{$bContact->id}", ['name' => 'Hijacked'])
            ->assertStatus(404);

        $this->assertSame('B Customer', $bContact->fresh()->name);
    }
}
