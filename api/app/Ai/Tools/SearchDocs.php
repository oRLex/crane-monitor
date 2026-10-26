<?php

namespace App\Ai\Tools;

use App\Ai\Knowledge\KnowledgeSearch;
use App\Domain\Scope;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;

class SearchDocs extends Tool
{
    public function __construct(private readonly KnowledgeSearch $search) {}

    public function name(): string
    {
        return 'search_docs';
    }

    public function description(): string
    {
        return 'Search crane manuals, safety procedures and the customer\'s own site documents. Returns the most relevant passages with a citation id like [D3§2]. Use it for limits, procedures and "what should we do" questions, and cite the ids you rely on.';
    }

    public function scope(): Scope
    {
        return Scope::DocsRead;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->description('What to look for, in plain words')->required(),
            'limit' => $schema->integer()->description('Passages to return, 1-8 (default 5)')->min(1)->max(8),
        ];
    }

    public function rules(): array
    {
        return ['query' => ['required', 'string', 'max:500'], 'limit' => ['sometimes', 'integer', 'between:1,8']];
    }

    public function handle(array $input, User $user): array
    {
        $hits = $this->search->search($input['query'], $user, $input['limit'] ?? 5);

        return ['passages' => array_map(fn ($h) => $h->toArray(), $hits)];
    }
}
