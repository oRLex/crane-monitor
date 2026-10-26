<?php

namespace App\Jobs;

use App\Ai\Knowledge\Chunker;
use App\Ai\Knowledge\Embeddings;
use App\Models\Document;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Throwable;

/** Chunk -> embed -> replace chunks atomically. Re-running on unchanged content is a no-op. */
class IngestDocument implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60];

    public function __construct(public readonly int $documentId)
    {
        $this->onQueue('ai');
    }

    public function uniqueId(): string
    {
        return (string) $this->documentId;
    }

    public function handle(Chunker $chunker, Embeddings $embeddings): void
    {
        $document = Document::query()->withoutGlobalScopes()->find($this->documentId);
        if ($document === null) {
            return;
        }
        if ($document->status === 'indexed' && $document->embedding_model === $embeddings->model()) {
            return;
        }

        $chunks = $chunker->chunk($document->title, $document->content);
        // Embed heading + text: the heading carries context the passage alone often lacks.
        $vectors = $chunks === [] ? [] : $embeddings->embed(array_map(fn ($c) => "{$c['heading']}\n{$c['content']}", $chunks), 'document');

        DB::transaction(function () use ($document, $chunks, $vectors, $embeddings) {
            $document->chunks()->delete();
            foreach ($chunks as $i => $chunk) {
                $id = DB::table('document_chunks')->insertGetId([
                    'document_id' => $document->id,
                    'tenant_id' => $document->tenant_id,
                    'ordinal' => $i + 1,
                    'heading' => mb_substr($chunk['heading'], 0, 250),
                    'content' => $chunk['content'],
                    'token_estimate' => (int) ceil(strlen($chunk['content']) / 4),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                DB::update('update document_chunks set embedding = ?::vector where id = ?', ['['.implode(',', $vectors[$i]).']', $id]);
            }
            $document->update(['status' => 'indexed', 'error' => null, 'chunk_count' => count($chunks), 'embedding_model' => $embeddings->model()]);
        });
    }

    public function failed(Throwable $e): void
    {
        Document::query()->withoutGlobalScopes()->whereKey($this->documentId)->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 250)]);
    }
}
