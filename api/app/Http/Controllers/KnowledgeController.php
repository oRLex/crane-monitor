<?php

namespace App\Http\Controllers;

use App\Ai\Knowledge\KnowledgeSearch;
use App\Jobs\IngestDocument;
use App\Models\Document;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KnowledgeController
{
    public function index(): JsonResponse
    {
        $docs = Document::query()->orderByRaw('tenant_id is not null desc')->orderBy('title')
            ->get(['id', 'tenant_id', 'title', 'source', 'status', 'error', 'chunk_count', 'embedding_model', 'updated_at']);

        return response()->json(['documents' => $docs]);
    }

    public function show(Document $document): JsonResponse
    {
        return response()->json([
            'document' => $document->only(['id', 'tenant_id', 'title', 'content', 'status', 'chunk_count', 'updated_at']),
            'chunks' => $document->chunks()->orderBy('ordinal')->get(['ordinal', 'heading']),
        ]);
    }

    /** Retrieval only (no LLM): shows what the assistant would read for a question. */
    public function search(Request $request, KnowledgeSearch $search): JsonResponse
    {
        $data = $request->validate(['q' => ['required', 'string', 'max:500']]);
        /** @var User $user */
        $user = $request->user();

        return response()->json(['passages' => array_map(fn ($h) => $h->toArray(), $search->search($data['q'], $user, 6))]);
    }

    /** Customers add their own documents (site rules, lift plans); indexing runs on the queue. */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'content' => ['required_without:file', 'nullable', 'string', 'max:200000'],
            'file' => ['required_without:content', 'nullable', 'file', 'mimes:md,txt', 'max:512'],
        ]);
        /** @var User $user */
        $user = $request->user();
        $content = $request->hasFile('file') ? (string) $request->file('file')?->get() : (string) $data['content'];

        $document = Document::query()->create([
            // Platform roles upload to the shared library; customers to their own tenant.
            'tenant_id' => $user->tenant_id,
            'title' => $data['title'],
            'source' => 'upload:'.now()->format('YmdHis').':'.$user->id,
            'content' => $content,
            'content_hash' => hash('sha256', $content),
            'uploaded_by' => $user->id,
        ]);
        IngestDocument::dispatch($document->id);

        return response()->json(['document' => $document->only(['id', 'title', 'status'])], 202);
    }

    public function destroy(Request $request, Document $document): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        // Customers can delete only their own tenant's documents, never the shared library.
        abort_if($document->tenant_id === null && $user->tenant_id !== null, 403, 'Platform documents are read-only');
        $document->delete();

        return response()->json(['ok' => true]);
    }
}
