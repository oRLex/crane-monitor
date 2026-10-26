<?php

namespace App\Ai\Llm;

interface LlmClient
{
    /** @throws LlmException */
    public function complete(LlmRequest $request): LlmResponse;

    public function model(): string;

    public function isMock(): bool;
}
