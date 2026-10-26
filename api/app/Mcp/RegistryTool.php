<?php

namespace App\Mcp;

use App\Ai\Tools\Tool as AppTool;
use App\Ai\Tools\ToolRegistry;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

/**
 * Exposes an app tool over MCP. Same definition, same ToolRegistry::call() path as the
 * in-app assistant, so scopes, tenant isolation, validation and logging are identical for
 * Claude Desktop / Claude Code / any MCP client.
 */
class RegistryTool extends Tool
{
    public function __construct(private readonly AppTool $tool) {}

    public function name(): string
    {
        return $this->tool->name();
    }

    public function title(): string
    {
        return ucfirst(str_replace('_', ' ', $this->tool->name()));
    }

    public function description(): string
    {
        return $this->tool->description();
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return $this->tool->schema($schema);
    }

    /** @return array<string, mixed> */
    public function annotations(): array
    {
        return $this->tool->mutating()
            ? ['readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => true, 'openWorldHint' => false]
            : ['readOnlyHint' => true, 'openWorldHint' => false];
    }

    public function handle(Request $request, ToolRegistry $registry): Response|ResponseFactory
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return Response::error('Unauthenticated');
        }
        $result = $registry->call($this->tool->name(), $request->all(), $user);

        return $result->ok ? Response::structured($result->data) : Response::error((string) $result->error);
    }
}
