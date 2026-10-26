<?php

namespace App\Ai\Knowledge;

use App\Domain\Scope;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Hybrid retrieval: pgvector cosine similarity + Postgres full-text, merged with reciprocal
 * rank fusion. Full-text rescues exact terms (model names, "load moment", numbers) that
 * embeddings blur; vectors rescue paraphrases. The tenant filter is part of the SQL, so a
 * customer can never retrieve another customer's documents, whatever the query says.
 */
class KnowledgeSearch
{
    private const CANDIDATES = 20;

    private const RRF_K = 60;

    public function __construct(private readonly Embeddings $embeddings) {}

    /** @return list<SearchHit> */
    public function search(string $query, User $user, int $limit = 5): array
    {
        [$tenantSql, $tenantBindings] = $this->tenantFilter($user);
        $vector = '['.implode(',', $this->embeddings->embed([$query], 'query')[0]).']';

        $semantic = DB::select(
            "select c.id from document_chunks c join documents d on d.id = c.document_id
             where d.status = 'indexed' and {$tenantSql}
             order by c.embedding <=> ?::vector limit ".self::CANDIDATES,
            [...$tenantBindings, $vector],
        );
        $lexical = DB::select(
            "select c.id from document_chunks c join documents d on d.id = c.document_id
             where d.status = 'indexed' and {$tenantSql} and c.tsv @@ websearch_to_tsquery('english', ?)
             order by ts_rank_cd(c.tsv, websearch_to_tsquery('english', ?)) desc limit ".self::CANDIDATES,
            [...$tenantBindings, $query, $query],
        );

        $scores = [];
        foreach ([$semantic, $lexical] as $ranking) {
            foreach (array_values($ranking) as $rank => $row) {
                $scores[$row->id] = ($scores[$row->id] ?? 0) + 1 / (self::RRF_K + $rank + 1);
            }
        }
        arsort($scores);
        $ids = array_slice(array_keys($scores), 0, $limit);
        if ($ids === []) {
            return [];
        }

        $rows = DB::table('document_chunks as c')->join('documents as d', 'd.id', '=', 'c.document_id')
            ->whereIn('c.id', $ids)
            ->get(['c.id', 'c.document_id', 'c.ordinal', 'c.heading', 'c.content', 'd.title', 'd.tenant_id'])
            ->keyBy('id');

        $hits = [];
        foreach ($ids as $id) {
            $row = $rows->get($id);
            if ($row === null) {
                continue;
            }
            $hits[] = new SearchHit(
                documentId: (int) $row->document_id,
                chunkId: (int) $id,
                ordinal: (int) $row->ordinal,
                documentTitle: $row->title,
                heading: $row->heading,
                content: $row->content,
                score: $scores[$id],
                tenantDocument: $row->tenant_id !== null,
            );
        }

        return $hits;
    }

    /** @return array{string, list<string>} */
    private function tenantFilter(User $user): array
    {
        if ($user->hasScope(Scope::TenantsAll)) {
            return ['true', []];
        }
        if ($user->tenant_id === null) {
            return ['d.tenant_id is null', []];
        }

        return ['(d.tenant_id is null or d.tenant_id = ?)', [$user->tenant_id]];
    }
}
