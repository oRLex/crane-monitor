<?php

namespace App\Ai\Tools;

use App\Domain\Scope;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\JsonSchema as JsonSchemaFactory;
use Illuminate\JsonSchema\Types\Type;

/**
 * One capability exposed to models — used by both the in-app assistant (Claude tool use)
 * and the MCP server. Authorization lives here, not in prompts: the registry checks the
 * scope and runs the tool inside the caller's tenant context.
 */
abstract class Tool
{
    abstract public function name(): string;

    abstract public function description(): string;

    abstract public function scope(): Scope;

    /** @return array<string, Type> */
    abstract public function schema(JsonSchema $schema): array;

    /**
     * @param  array<string, mixed>  $input  validated input
     * @return array<string, mixed>
     */
    abstract public function handle(array $input, User $user): array;

    /** Validation rules applied before handle() — models do send malformed input. */
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }

    /** Changes state: the assistant asks the user to confirm instead of executing. */
    public function mutating(): bool
    {
        return false;
    }

    /** @return array<string, mixed> */
    public function inputSchema(): array
    {
        $schema = JsonSchemaFactory::object($this->schema(...))->toArray();
        $schema['properties'] ??= (object) [];
        $schema['additionalProperties'] = false;

        return $schema;
    }

    /** @return array{name: string, description: string, input_schema: array<string, mixed>} */
    public function definition(): array
    {
        return ['name' => $this->name(), 'description' => $this->description(), 'input_schema' => $this->inputSchema()];
    }
}
