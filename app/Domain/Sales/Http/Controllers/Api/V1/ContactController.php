<?php

namespace App\Domain\Sales\Http\Controllers\Api\V1;

use App\Domain\Sales\Http\Requests\StoreContactRequest;
use App\Domain\Sales\Http\Requests\UpdateContactRequest;
use App\Domain\Sales\Http\Resources\ContactResource;
use App\Http\Controllers\Api\V1\Controller;
use App\Models\Contact;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ContactController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Contact::class);

        $contacts = Contact::query()
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')))
            ->when($request->filled('search'), fn ($q) => $q->where('name', 'like', '%'.$request->string('search').'%'))
            ->orderByDesc('id')
            ->cursorPaginate((int) $request->integer('per_page', 25));

        return ContactResource::collection($contacts);
    }

    public function show(Contact $contact): ContactResource
    {
        $this->authorize('view', $contact);

        return ContactResource::make($contact);
    }

    public function store(StoreContactRequest $request): \Illuminate\Http\JsonResponse
    {
        $contact = Contact::create($this->attributesFrom($request));

        return ContactResource::make($contact)->response()->setStatusCode(201);
    }

    public function update(UpdateContactRequest $request, Contact $contact): ContactResource
    {
        $contact->update($this->attributesFrom($request));

        return ContactResource::make($contact);
    }

    public function destroy(Contact $contact): \Illuminate\Http\Response
    {
        $this->authorize('delete', $contact);

        $contact->delete();

        return response()->noContent();
    }

    /**
     * Validated input, plus the one derived field a client must never
     * compute itself: `tax_id_index` is a blind index over the tax ID so it
     * can be looked up without decrypting every row, and letting a client
     * choose its own hash would defeat that. Same rule App\Livewire\Customers\Form
     * and App\Services\SyncEngine both already apply.
     */
    protected function attributesFrom(Request $request): array
    {
        $attributes = $request->validated();

        if (array_key_exists('tax_id', $attributes)) {
            $taxId = trim((string) $attributes['tax_id']);
            $attributes['tax_id'] = $taxId !== '' ? $taxId : null;
            $attributes['tax_id_index'] = $taxId !== ''
                ? hash('sha256', preg_replace('/\W+/', '', $taxId))
                : null;
        }

        return $attributes;
    }
}
