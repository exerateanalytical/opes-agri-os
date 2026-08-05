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

class ContactsApiTest extends TestCase
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

        $this->token = app(ApiTokenIssuer::class)
            ->issue($this->owner, $this->company, 'test', ['*'])
            ->plainTextToken;
    }

    protected function api(): \Illuminate\Testing\TestResponse|static
    {
        return $this->withHeader('Authorization', "Bearer {$this->token}");
    }

    public function test_it_creates_a_contact(): void
    {
        $response = $this->api()->postJson('/api/v1/contacts', [
            'name' => 'Jane Farmer',
            'email' => 'jane@example.com',
            'phones' => ['+237600000000'],
            'tax_id' => 'M012345678',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Jane Farmer')
            ->assertJsonPath('data.email', 'jane@example.com')
            ->assertJsonPath('data.tax_id', 'M012345678')
            ->assertJsonMissingPath('data.tax_id_index')
            ->assertJsonMissingPath('data.company_id');

        $contact = Contact::query()->where('company_id', $this->company->id)->sole();
        $this->assertSame('Jane Farmer', $contact->name);
        $this->assertNotNull($contact->tax_id_index);
    }

    public function test_it_rejects_a_contact_with_no_name(): void
    {
        $this->api()->postJson('/api/v1/contacts', [])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed')
            ->assertJsonPath('error.details.name.0', 'The name field is required.');
    }

    public function test_it_cannot_set_server_owned_fields(): void
    {
        $otherCompany = Company::create([
            'slug' => 'other-'.Str::lower(Str::random(4)),
            'name' => 'Other Ltd',
            'owner_id' => User::factory()->create()->id,
            'currency' => 'USD',
        ]);

        $response = $this->api()->postJson('/api/v1/contacts', [
            'name' => 'Jane Farmer',
            'company_id' => $otherCompany->id,
            'balance' => 999999,
        ]);

        $response->assertCreated();

        $contact = Contact::query()->where('company_id', $this->company->id)->sole();
        $this->assertSame($this->company->id, $contact->company_id);
        $this->assertSame('0.00', $contact->balance);
    }

    public function test_it_lists_and_shows_contacts(): void
    {
        app(CurrentCompany::class)->set($this->company);
        $contact = Contact::create(['name' => 'Existing Co']);

        $this->api()->getJson('/api/v1/contacts')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Existing Co');

        $this->api()->getJson("/api/v1/contacts/{$contact->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $contact->id);
    }

    public function test_it_updates_a_contact(): void
    {
        app(CurrentCompany::class)->set($this->company);
        $contact = Contact::create(['name' => 'Old Name']);

        $this->api()->patchJson("/api/v1/contacts/{$contact->id}", ['name' => 'New Name'])
            ->assertOk()
            ->assertJsonPath('data.name', 'New Name');
    }

    public function test_it_deletes_a_contact(): void
    {
        app(CurrentCompany::class)->set($this->company);
        $contact = Contact::create(['name' => 'To Delete']);

        $this->api()->deleteJson("/api/v1/contacts/{$contact->id}")->assertNoContent();

        $this->assertSame(0, Contact::query()->where('id', $contact->id)->count());
    }

    public function test_a_token_without_the_create_ability_is_refused(): void
    {
        $viewOnlyToken = app(ApiTokenIssuer::class)
            ->issue($this->owner, $this->company, 'view-only', ['customers.view'])
            ->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$viewOnlyToken}")
            ->postJson('/api/v1/contacts', ['name' => 'Nope'])
            ->assertStatus(403);
    }
}
