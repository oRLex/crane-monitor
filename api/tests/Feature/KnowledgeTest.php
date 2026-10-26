<?php

namespace Tests\Feature;

use App\Ai\Knowledge\KnowledgeSearch;
use App\Models\Document;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class KnowledgeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('knowledge:ingest', ['--sync' => true]);
    }

    public function test_library_is_chunked_and_embedded(): void
    {
        $doc = Document::query()->where('title', 'Wind limits and out-of-service procedure')->firstOrFail();

        $this->assertSame('indexed', $doc->status);
        $this->assertGreaterThan(3, $doc->chunk_count);
        $this->assertSame(0, \DB::table('document_chunks')->whereNull('embedding')->count());
    }

    public function test_ingest_is_idempotent(): void
    {
        Artisan::call('knowledge:ingest', ['--sync' => true]);

        $this->assertStringContainsString('0 document(s)', Artisan::output());
    }

    public function test_retrieves_the_relevant_procedure(): void
    {
        $hits = app(KnowledgeSearch::class)->search('what to do when the load moment limiter trips', $this->user(self::MANAGER), 3);

        $this->assertSame('Load moment limiter and overload procedure', $hits[0]->documentTitle);
        $this->assertMatchesRegularExpression('/^D\d+§\d+$/', $hits[0]->citation());
    }

    public function test_customers_never_retrieve_another_customers_documents(): void
    {
        $query = 'site rules facade panels wind limit Podil';

        $budinvest = array_map(fn ($h) => $h->documentTitle, app(KnowledgeSearch::class)->search($query, $this->user(self::MANAGER), 8));
        $skyline = array_map(fn ($h) => $h->documentTitle, app(KnowledgeSearch::class)->search($query, $this->user(self::VIEWER), 8));

        $this->assertContains('Podil Residence — site lifting rules', $budinvest);
        $this->assertNotContains('Podil Residence — site lifting rules', $skyline);
        $this->assertNotContains('Wola Tower — lift planning notes', $budinvest);
    }

    public function test_document_api_is_tenant_scoped(): void
    {
        $podil = Document::query()->where('title', 'like', 'Podil%')->firstOrFail();

        $this->getAs(self::VIEWER, "/api/knowledge/documents/{$podil->id}")->assertNotFound();
        $titles = array_column($this->getAs(self::VIEWER, '/api/knowledge/documents')->json('documents'), 'title');
        $this->assertNotContains($podil->title, $titles);
    }

    public function test_upload_requires_docs_write_and_lands_in_own_tenant(): void
    {
        $this->as(self::VIEWER)->postJson('/api/knowledge/documents', ['title' => 'x', 'content' => 'y'])->assertForbidden();

        $id = $this->as(self::MANAGER)->postJson('/api/knowledge/documents', ['title' => 'Night shift rules', 'content' => "## Lighting\nFloodlights on before lifting."])
            ->assertStatus(202)->json('document.id');

        $doc = Document::query()->findOrFail($id);
        $this->assertSame('t-budinvest', $doc->tenant_id);
        $this->assertSame('indexed', $doc->status); // queue is sync in tests
    }
}
