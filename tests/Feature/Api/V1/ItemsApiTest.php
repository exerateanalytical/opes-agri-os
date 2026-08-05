<?php

namespace Tests\Feature\Api\V1;

use App\Models\Company;
use App\Models\Item;
use App\Models\Role;
use App\Models\User;
use App\Services\ApiTokenIssuer;
use App\Support\CurrentCompany;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ItemsApiTest extends TestCase
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

    protected function api()
    {
        return $this->withHeader('Authorization', "Bearer {$this->token}");
    }

    public function test_it_creates_an_item(): void
    {
        $response = $this->api()->postJson('/api/v1/items', [
            'name' => 'Bag of Fertiliser',
            'price' => 12500,
            'sku' => 'FERT-50KG',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Bag of Fertiliser')
            ->assertJsonPath('data.sku', 'FERT-50KG');

        $item = Item::query()->where('company_id', $this->company->id)->sole();
        $this->assertSame('12500.00', $item->price);
    }

    public function test_it_rejects_a_duplicate_sku(): void
    {
        app(CurrentCompany::class)->set($this->company);
        Item::create(['name' => 'Existing', 'sku' => 'DUP-1']);

        $this->api()->postJson('/api/v1/items', ['name' => 'New', 'sku' => 'DUP-1'])
            ->assertStatus(422)
            ->assertJsonPath('error.details.sku.0', 'The sku has already been taken.');
    }

    public function test_a_sku_can_repeat_across_companies(): void
    {
        $otherOwner = User::factory()->create();
        $otherCompany = Company::create([
            'slug' => 'other-'.Str::lower(Str::random(4)),
            'name' => 'Other Ltd',
            'owner_id' => $otherOwner->id,
            'currency' => 'USD',
        ]);
        app(CurrentCompany::class)->set($otherCompany);
        Item::create(['name' => 'Theirs', 'sku' => 'SHARED-SKU']);

        $this->api()->postJson('/api/v1/items', ['name' => 'Ours', 'sku' => 'SHARED-SKU'])
            ->assertCreated();
    }

    public function test_category_id_must_belong_to_the_same_company(): void
    {
        $otherOwner = User::factory()->create();
        $otherCompany = Company::create([
            'slug' => 'other-'.Str::lower(Str::random(4)),
            'name' => 'Other Ltd',
            'owner_id' => $otherOwner->id,
            'currency' => 'USD',
        ]);
        app(CurrentCompany::class)->set($otherCompany);
        $foreignCategory = \App\Models\Category::create(['name' => 'Theirs', 'slug' => 'theirs']);

        $this->api()->postJson('/api/v1/items', ['name' => 'Ours', 'category_id' => $foreignCategory->id])
            ->assertStatus(422)
            ->assertJsonPath('error.details.category_id.0', 'The selected category id is invalid.');
    }

    public function test_it_updates_and_deletes_an_item(): void
    {
        app(CurrentCompany::class)->set($this->company);
        $item = Item::create(['name' => 'Old', 'price' => 100]);

        $this->api()->patchJson("/api/v1/items/{$item->id}", ['price' => 200])
            ->assertOk()
            ->assertJsonPath('data.price', '200.00');

        $this->api()->deleteJson("/api/v1/items/{$item->id}")->assertNoContent();
    }
}
