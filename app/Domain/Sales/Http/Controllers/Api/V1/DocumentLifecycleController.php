<?php

namespace App\Domain\Sales\Http\Controllers\Api\V1;

use App\Domain\Sales\Http\Requests\ConvertDocumentRequest;
use App\Domain\Sales\Http\Requests\VoidDocumentRequest;
use App\Domain\Sales\Http\Resources\DocumentResource;
use App\Enums\DocumentType;
use App\Http\Controllers\Api\V1\Controller;
use App\Models\Document;
use App\Services\DocumentConverter;
use App\Services\DocumentIssuer;
use Illuminate\Http\Request;
use RuntimeException;

class DocumentLifecycleController extends Controller
{
    public function issue(Request $request, Document $document, DocumentIssuer $issuer): DocumentResource
    {
        $this->authorize('issue', $document);

        // No client-supplied number: that path exists for a device replaying
        // a number it already printed offline (see DocumentIssuer::issue's
        // docblock), which does not apply to a token-authenticated API call.
        $issued = $issuer->issue($document, $request->user());

        return DocumentResource::make($issued->load(['contact', 'lines']));
    }

    public function void(VoidDocumentRequest $request, Document $document, DocumentConverter $converter): DocumentResource
    {
        $voided = $converter->void($document, $request->user(), $request->validated('reason'));

        return DocumentResource::make($voided->load(['contact', 'lines']));
    }

    public function convert(ConvertDocumentRequest $request, Document $document, DocumentConverter $converter): DocumentResource
    {
        $amount = $request->validated('amount');

        if ($amount !== null) {
            if ($document->type !== DocumentType::Invoice) {
                throw new RuntimeException('Only an invoice can be partially credited with an amount.');
            }

            $result = $converter->creditNote($document, $request->user(), (float) $amount, $request->validated('reason'));

            return DocumentResource::make($result->load(['contact', 'lines']));
        }

        if (! $converter->canConvert($document)) {
            throw new RuntimeException('This document cannot be converted.');
        }

        $result = $converter->convert($document, $request->user());

        return DocumentResource::make($result->load(['contact', 'lines']));
    }
}
