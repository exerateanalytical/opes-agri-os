<?php

namespace Tests\Feature\Api\V1;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Document;
use App\Models\Item;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Role;
use App\Models\User;
use App\Services\ApiTokenIssuer;
use App\Support\CurrentCompany;
use App\Support\Permissions;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Every endpoint checked individually already has its own "wrong ability"
 * test in its resource's *ApiTest. This is the cross-cutting sweep once the
 * full surface exists: two guarantees those per-endpoint tests can't give on
 * their own.
 *
 * 1. STRUCTURAL — every route under api/v1 (besides the unauthenticated-
 *    proof `/ping`) carries an `abilities:` middleware at all. A route added
 *    later without one would still 401/200 for any authenticated token
 *    regardless of what it can do — silently unprotected rather than loudly
 *    broken, which is the failure mode worth guarding against structurally.
 * 2. RUNTIME — for each of those, a token holding every OTHER ability gets
 *    403 on it specifically, proving the declared ability is the one
 *    actually enforced (not, say, a copy-pasted wrong slug that happens to
 *    still 403 for an unrelated reason).
 */
class AbilityEnforcementTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected Company $company;

    protected Contact $contact;

    protected Item $item;

    protected Document $draftDocument;

    protected Document $issuedDocument;

    protected Payment $payment;

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

        $this->contact = Contact::create(['name' => 'A Customer']);
        $this->item = Item::create(['name' => 'An Item', 'price' => 10]);

        $this->draftDocument = Document::create([
            'type' => DocumentType::Quotation,
            'contact_id' => $this->contact->id,
            'status' => DocumentStatus::Draft,
            'issue_date' => now()->toDateString(),
            'currency' => 'USD',
            'subtotal' => 100,
            'total' => 100,
            'balance' => 100,
        ]);

        $this->issuedDocument = Document::create([
            'type' => DocumentType::Invoice,
            'contact_id' => $this->contact->id,
            'status' => DocumentStatus::Issued,
            'number' => 'INV-TEST-1',
            'issue_date' => now()->toDateString(),
            'issued_at' => now(),
            'currency' => 'USD',
            'subtotal' => 100,
            'total' => 100,
            'balance' => 100,
        ]);

        $this->payment = Payment::create([
            'contact_id' => $this->contact->id,
            'method' => 'cash',
            'amount' => 50,
            'currency' => 'USD',
            'received_at' => now(),
            'received_by' => $this->owner->id,
        ]);
        PaymentAllocation::create([
            'payment_id' => $this->payment->id,
            'document_id' => $this->issuedDocument->id,
            'amount' => 50,
        ]);
    }

    /** @return array<int, array{method: string, uri: string}> */
    protected function endpoints(): array
    {
        return [
            ['method' => 'GET', 'uri' => '/api/v1/contacts'],
            ['method' => 'POST', 'uri' => '/api/v1/contacts'],
            ['method' => 'GET', 'uri' => "/api/v1/contacts/{$this->contact->id}"],
            ['method' => 'PATCH', 'uri' => "/api/v1/contacts/{$this->contact->id}"],
            ['method' => 'DELETE', 'uri' => "/api/v1/contacts/{$this->contact->id}"],
            ['method' => 'GET', 'uri' => '/api/v1/items'],
            ['method' => 'POST', 'uri' => '/api/v1/items'],
            ['method' => 'GET', 'uri' => "/api/v1/items/{$this->item->id}"],
            ['method' => 'PATCH', 'uri' => "/api/v1/items/{$this->item->id}"],
            ['method' => 'DELETE', 'uri' => "/api/v1/items/{$this->item->id}"],
            ['method' => 'GET', 'uri' => '/api/v1/documents'],
            ['method' => 'POST', 'uri' => '/api/v1/documents'],
            ['method' => 'GET', 'uri' => "/api/v1/documents/{$this->draftDocument->id}"],
            ['method' => 'PATCH', 'uri' => "/api/v1/documents/{$this->draftDocument->id}"],
            ['method' => 'POST', 'uri' => "/api/v1/documents/{$this->draftDocument->id}/issue"],
            ['method' => 'POST', 'uri' => "/api/v1/documents/{$this->issuedDocument->id}/void"],
            ['method' => 'POST', 'uri' => "/api/v1/documents/{$this->draftDocument->id}/convert"],
            ['method' => 'POST', 'uri' => "/api/v1/documents/{$this->issuedDocument->id}/payments"],
            ['method' => 'GET', 'uri' => '/api/v1/payments'],
            ['method' => 'GET', 'uri' => "/api/v1/payments/{$this->payment->id}"],
        ];
    }

    public function test_every_documented_endpoint_declares_an_abilities_middleware(): void
    {
        $undeclared = [];

        foreach (Route::getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'api/v1/') || $route->uri() === 'api/v1/ping') {
                continue;
            }

            $hasAbility = collect($route->gatherMiddleware())
                ->contains(fn ($m) => str_starts_with($m, 'abilities:'));

            if (! $hasAbility) {
                $undeclared[] = $route->methods()[0].' '.$route->uri();
            }
        }

        $this->assertSame([], $undeclared, 'Route(s) with no abilities: middleware: '.implode(', ', $undeclared));
    }

    public function test_the_declared_ability_for_every_endpoint_is_the_one_enforced(): void
    {
        $allSlugs = Permissions::slugs();

        foreach (Route::getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'api/v1/') || $route->uri() === 'api/v1/ping') {
                continue;
            }

            $abilityMiddleware = collect($route->gatherMiddleware())
                ->first(fn ($m) => str_starts_with($m, 'abilities:'));

            if ($abilityMiddleware === null) {
                continue; // Already failed by the structural test above.
            }

            $ability = Str::after($abilityMiddleware, 'abilities:');
            $method = $route->methods()[0];
            $uri = '/'.ltrim($route->uri(), '/');

            // A resolved path (e.g. /api/v1/documents/{document}) won't match
            // any entry in endpoints() by URI directly; match by matching the
            // route name against the fixed endpoints() list's resolved URIs.
            $matching = collect($this->endpoints())
                ->first(fn ($e) => $e['method'] === $method && $this->uriMatchesRoute($e['uri'], $route));

            if ($matching === null) {
                $this->fail("No fixture endpoint covers {$method} {$uri} (ability: {$ability}) — add one to endpoints().");
            }

            $restrictedToken = app(ApiTokenIssuer::class)
                ->issue($this->owner, $this->company, 'sweep', array_values(array_diff($allSlugs, [$ability])))
                ->plainTextToken;

            Auth::forgetGuards();

            $response = $this->withHeader('Authorization', "Bearer {$restrictedToken}")
                ->json($method, $matching['uri']);

            $this->assertSame(
                403,
                $response->getStatusCode(),
                "{$method} {$matching['uri']} did not 403 for a token missing '{$ability}' (got {$response->getStatusCode()})."
            );
        }
    }

    protected function uriMatchesRoute(string $concreteUri, $route): bool
    {
        $pattern = preg_replace('#\{[^}]+\}#', '[^/]+', $route->uri());

        return (bool) preg_match('#^'.$pattern.'$#', ltrim($concreteUri, '/'));
    }
}
