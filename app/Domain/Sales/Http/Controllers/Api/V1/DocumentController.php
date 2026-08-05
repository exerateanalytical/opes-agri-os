<?php

namespace App\Domain\Sales\Http\Controllers\Api\V1;

use App\Domain\Sales\Actions\CreateDraftDocumentAction;
use App\Domain\Sales\Http\Requests\StoreDocumentRequest;
use App\Domain\Sales\Http\Requests\UpdateDocumentRequest;
use App\Domain\Sales\Http\Resources\DocumentResource;
use App\Enums\DocumentType;
use App\Http\Controllers\Api\V1\Controller;
use App\Models\Company;
use App\Models\Document;
use App\Support\CurrentCompany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DocumentController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Document::class);

        $documents = Document::query()
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('contact_id'), fn ($q) => $q->where('contact_id', $request->string('contact_id')))
            ->orderByDesc('id')
            ->cursorPaginate((int) $request->integer('per_page', 25));

        return DocumentResource::collection($documents);
    }

    public function show(Document $document): DocumentResource
    {
        $this->authorize('view', $document);

        return DocumentResource::make($document->load(['contact', 'lines']));
    }

    public function store(StoreDocumentRequest $request, CreateDraftDocumentAction $action): JsonResponse
    {
        /** @var Company $company */
        $company = app(CurrentCompany::class)->get();

        $document = $action->create(
            $company,
            $request->user(),
            DocumentType::from($request->validated('type')),
            $request->validated(),
        );

        return DocumentResource::make($document->load(['contact', 'lines']))->response()->setStatusCode(201);
    }

    public function update(UpdateDocumentRequest $request, Document $document): DocumentResource
    {
        $document->update($request->validated());

        return DocumentResource::make($document->load(['contact', 'lines']));
    }
}
