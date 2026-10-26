<?php

return [
    // No key -> deterministic mock LLM (demo without an API key, CI, tests).
    'anthropic_key' => env('ANTHROPIC_API_KEY'),
    'model' => env('AI_MODEL', 'claude-opus-5-5'),
    // Chat and tool routing don't benefit from deep thinking; reports use their own setting.
    'effort' => env('AI_EFFORT', 'low'),
    'report_effort' => env('AI_REPORT_EFFORT', 'medium'),
    'timeout' => (float) env('AI_TIMEOUT', 60),
    'max_retries' => (int) env('AI_MAX_RETRIES', 2),
    'max_tool_iterations' => 6,

    'daily_token_budget' => (int) env('AI_DAILY_TOKEN_BUDGET', 400_000),

    // USD per million tokens, used for the usage ledger (cache reads ~0.1x input, writes 1.25x).
    'pricing' => [
        'claude-opus-5-5' => ['input' => 4.00, 'output' => 20.00, 'cache_read' => 0.20, 'cache_write' => 5.00],
        'claude-sonnet-5-5' => ['input' => 2.00, 'output' => 10.00, 'cache_read' => 0.20, 'cache_write' => 2.50],
        'claude-haiku-4-5' => ['input' => 1.00, 'output' => 5.00, 'cache_read' => 0.10, 'cache_write' => 1.25],
        'voyage-3.5' => ['input' => 0.06, 'output' => 0, 'cache_read' => 0, 'cache_write' => 0],
    ],

    // stdio MCP server (php artisan mcp:start cranewatch) acts as this user.
    'mcp_local_user' => env('MCP_LOCAL_USER'),

    'embeddings' => [
        'voyage_key' => env('VOYAGE_API_KEY'),
        'model' => env('VOYAGE_MODEL', 'voyage-3.5'),
        'dimensions' => 1024,
    ],
];
