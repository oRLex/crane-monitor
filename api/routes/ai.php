<?php

use App\Mcp\CraneWatchServer;
use Laravel\Mcp\Facades\Mcp;

// Remote MCP (Streamable HTTP). Authenticate with a Sanctum token — ideally a narrower one,
// e.g. POST /api/auth/login {"email": "...", "abilities": ["cranes:read", "alarms:read", "docs:read"]}.
Mcp::web('/mcp', CraneWatchServer::class)->middleware(['auth:sanctum', 'tenant', 'throttle:api']);

// Local stdio server for Claude Code / Desktop on a dev machine:
//   MCP_LOCAL_USER=operator@demo.io php artisan mcp:start cranewatch
Mcp::local('cranewatch', CraneWatchServer::class);
