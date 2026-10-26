<?php

namespace App\Console\Commands;

use App\Jobs\IngestDocument;
use App\Models\Document;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Syncs the bundled library (resources/knowledge) into the documents table: platform
 * documents at the top level, tenant documents under tenants/{tenant_id}/. Unchanged files
 * are skipped by content hash, so this is safe to run on every deploy.
 */
class IngestKnowledge extends Command
{
    protected $signature = 'knowledge:ingest {--sync : Index in this process instead of queueing} {--force : Re-embed everything}';

    protected $description = 'Index the bundled knowledge library for RAG';

    public function handle(): int
    {
        $root = resource_path('knowledge');
        $queued = 0;
        foreach (File::allFiles($root) as $file) {
            if ($file->getExtension() !== 'md') {
                continue;
            }
            $relative = str_replace('\\', '/', $file->getRelativePathname());
            $tenant = preg_match('#^tenants/([^/]+)/#', $relative, $m) ? $m[1] : null;
            $content = $file->getContents();
            $title = preg_match('/^#\s+(.+)$/m', $content, $t) ? trim($t[1]) : $file->getFilenameWithoutExtension();
            $hash = hash('sha256', $content);

            $doc = Document::query()->withoutGlobalScopes()->firstOrNew(['tenant_id' => $tenant, 'source' => "library:{$relative}"]);
            if ($doc->exists && $doc->content_hash === $hash && ! $this->option('force')) {
                continue;
            }
            $doc->fill(['title' => $title, 'content' => $content, 'content_hash' => $hash, 'status' => 'pending'])->save();

            $this->option('sync') ? IngestDocument::dispatchSync($doc->id) : IngestDocument::dispatch($doc->id);
            $queued++;
            $this->line("  {$relative}".($tenant ? " ({$tenant})" : ''));
        }
        $this->info("{$queued} document(s) ".($this->option('sync') ? 'indexed' : 'queued for indexing').'.');

        return self::SUCCESS;
    }
}
