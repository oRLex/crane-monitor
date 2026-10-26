<?php

namespace App\Ai\Tools;

use App\Auth\TenantContext;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

class ToolRegistry
{
    /** @var array<string, Tool> */
    private array $tools = [];

    /** @param iterable<Tool> $tools */
    public function __construct(iterable $tools, private readonly TenantContext $context)
    {
        foreach ($tools as $tool) {
            $this->tools[$tool->name()] = $tool;
        }
    }

    /**
     * Tools the user may call. Unauthorized tools are not even advertised to the model.
     *
     * @return list<Tool>
     */
    public function for(User $user): array
    {
        return array_values(array_filter($this->tools, fn (Tool $t) => $user->hasScope($t->scope())));
    }

    /** @return list<Tool> */
    public function all(): array
    {
        return array_values($this->tools);
    }

    public function get(string $name): ?Tool
    {
        return $this->tools[$name] ?? null;
    }

    /**
     * Runs a tool for a user. Failures become error results the model can read and recover
     * from, never exceptions that kill the conversation.
     *
     * @param  array<string, mixed>  $input
     */
    public function call(string $name, array $input, User $user): ToolResult
    {
        $tool = $this->tools[$name] ?? null;
        if ($tool === null) {
            return ToolResult::error("Unknown tool: {$name}");
        }
        if (! $user->hasScope($tool->scope())) {
            return ToolResult::error("Not allowed: requires scope {$tool->scope()->value}");
        }
        $validator = Validator::make($input, $tool->rules());
        if ($validator->fails()) {
            return ToolResult::error('Invalid input: '.implode(' ', $validator->errors()->all()));
        }

        $started = microtime(true);
        try {
            $data = $this->context->runAs($user, fn () => $tool->handle($validator->validated(), $user));
            $result = ToolResult::ok($data);
        } catch (ModelNotFoundException) {
            // Same as the REST API: other tenants' ids look like missing ids.
            $result = ToolResult::error('Not found');
        } catch (Throwable $e) {
            report($e);
            $result = ToolResult::error('Tool failed');
        }
        Log::info('ai.tool', ['tool' => $name, 'user' => $user->id, 'ok' => $result->ok, 'ms' => (int) ((microtime(true) - $started) * 1000)]);

        return $result;
    }
}
