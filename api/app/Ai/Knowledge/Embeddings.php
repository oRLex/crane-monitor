<?php

namespace App\Ai\Knowledge;

interface Embeddings
{
    /**
     * @param  list<string>  $texts
     * @param  'document'|'query'  $inputType
     * @return list<list<float>> unit-length vectors
     */
    public function embed(array $texts, string $inputType): array;

    public function model(): string;
}
