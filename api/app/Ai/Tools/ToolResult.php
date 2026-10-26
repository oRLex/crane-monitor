<?php

namespace App\Ai\Tools;

final readonly class ToolResult
{
    /** @param array<string, mixed> $data */
    private function __construct(public bool $ok, public array $data, public ?string $error = null) {}

    /** @param array<string, mixed> $data */
    public static function ok(array $data): self
    {
        return new self(true, $data);
    }

    public static function error(string $message): self
    {
        return new self(false, [], $message);
    }

    public function toJson(): string
    {
        return json_encode($this->ok ? $this->data : ['error' => $this->error], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
