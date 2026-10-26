<?php

namespace App\Ai\Knowledge;

use Illuminate\Support\Facades\Http;

/** Voyage AI embeddings (Anthropic's recommended embedding provider), 1024 dims. */
class VoyageEmbeddings implements Embeddings
{
    public function __construct(private readonly string $apiKey, private readonly string $model, private readonly int $dimensions) {}

    public function embed(array $texts, string $inputType): array
    {
        $out = [];
        foreach (array_chunk($texts, 64) as $batch) {
            $response = Http::withToken($this->apiKey)
                ->timeout(30)
                ->retry(3, 500, throw: true)
                ->post('https://api.voyageai.com/v1/embeddings', [
                    'model' => $this->model,
                    'input' => $batch,
                    'input_type' => $inputType,
                    'output_dimension' => $this->dimensions,
                ])
                ->throw()
                ->json();
            foreach ($response['data'] as $row) {
                $out[] = array_values(array_map(floatval(...), $row['embedding']));
            }
        }

        return $out;
    }

    public function model(): string
    {
        return $this->model;
    }
}
