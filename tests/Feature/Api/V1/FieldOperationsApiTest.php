<?php

namespace Tests\Feature\Api\V1;

use App\Models\Company;
use App\Models\Farm;
use App\Models\Field;
use App\Models\Role;
use App\Models\User;
use App\Services\ApiTokenIssuer;
use App\Support\CurrentCompany;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Tests\TestCase;

class FieldOperationsApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected Company $company;

    protected Field $field;

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

        $farm = Farm::create(['name' => 'Green Valley Farm']);
        $this->field = Field::create(['farm_id' => $farm->id, 'name' => 'North Plot']);

        $this->token = app(ApiTokenIssuer::class)
            ->issue($this->owner, $this->company, 'test', ['*'])
            ->plainTextToken;
    }

    protected function api()
    {
        return $this->withHeader('Authorization', "Bearer {$this->token}");
    }

    public function test_it_records_a_soil_test(): void
    {
        $response = $this->api()->postJson("/api/v1/fields/{$this->field->id}/soil-tests", [
            'tested_on' => '2026-08-01',
            'ph' => 6.5,
            'nitrogen_ppm' => 40,
            'recommendations' => 'Add lime to raise pH slightly.',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.ph', '6.50')
            ->assertJsonPath('data.nitrogen_ppm', '40.00');

        $this->api()->getJson("/api/v1/fields/{$this->field->id}/soil-tests")
            ->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_ph_must_be_within_range(): void
    {
        $this->api()->postJson("/api/v1/fields/{$this->field->id}/soil-tests", [
            'tested_on' => '2026-08-01',
            'ph' => 20,
        ])->assertStatus(422);
    }

    public function test_it_records_an_irrigation_log(): void
    {
        $response = $this->api()->postJson("/api/v1/fields/{$this->field->id}/irrigation-logs", [
            'irrigated_on' => '2026-08-01',
            'method' => 'drip',
            'duration_minutes' => 45,
            'volume_liters' => 500,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.method', 'drip')
            ->assertJsonPath('data.duration_minutes', 45);

        $this->api()->getJson("/api/v1/fields/{$this->field->id}/irrigation-logs")
            ->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_a_token_without_the_record_soil_test_ability_is_refused(): void
    {
        $limitedToken = app(ApiTokenIssuer::class)
            ->issue($this->owner, $this->company, 'view-only', ['farms.view'])
            ->plainTextToken;

        Auth::forgetGuards();

        $this->withHeader('Authorization', "Bearer {$limitedToken}")
            ->postJson("/api/v1/fields/{$this->field->id}/soil-tests", ['tested_on' => '2026-08-01'])
            ->assertStatus(403);
    }

    public function test_a_token_without_the_record_irrigation_ability_is_refused(): void
    {
        $limitedToken = app(ApiTokenIssuer::class)
            ->issue($this->owner, $this->company, 'view-only', ['farms.view'])
            ->plainTextToken;

        Auth::forgetGuards();

        $this->withHeader('Authorization', "Bearer {$limitedToken}")
            ->postJson("/api/v1/fields/{$this->field->id}/irrigation-logs", ['irrigated_on' => '2026-08-01'])
            ->assertStatus(403);
    }

    public function test_field_operations_are_scoped_to_their_company(): void
    {
        $otherOwner = User::factory()->create();
        $otherCompany = Company::create([
            'slug' => 'other-'.Str::lower(Str::random(4)),
            'name' => 'Other Ltd',
            'owner_id' => $otherOwner->id,
            'currency' => 'USD',
        ]);
        app(CurrentCompany::class)->set($otherCompany);
        $theirFarm = Farm::create(['name' => 'Theirs']);
        $theirField = Field::create(['farm_id' => $theirFarm->id, 'name' => 'Theirs']);

        $this->api()->getJson("/api/v1/fields/{$theirField->id}/soil-tests")->assertStatus(404);
    }
}
