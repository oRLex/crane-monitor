<?php

namespace App\Http\Controllers;

use App\Ai\Assistant\AssistantService;
use App\Ai\TokenBudget;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\Alarm;
use App\Models\Crane;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\StreamedEvent;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class AssistantController
{
    public function index(Request $request): JsonResponse
    {
        $conversations = AiConversation::query()->where('user_id', $this->user($request)->id)
            ->latest('updated_at')->limit(30)->get(['id', 'title', 'crane_id', 'alarm_id', 'updated_at']);

        return response()->json(['conversations' => $conversations]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'crane_id' => ['nullable', 'string', 'max:40'],
            'alarm_id' => ['nullable', 'string', 'max:40'],
            'title' => ['nullable', 'string', 'max:80'],
        ]);
        $user = $this->user($request);
        // Focus ids are resolved through the tenant scope: another tenant's id 404s here.
        $alarm = isset($data['alarm_id']) ? Alarm::query()->findOrFail((string) $data['alarm_id']) : null;
        $crane = isset($data['crane_id']) ? Crane::query()->findOrFail((string) $data['crane_id']) : $alarm?->crane;

        $conversation = AiConversation::query()->create([
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'crane_id' => $crane?->id,
            'alarm_id' => $alarm?->id,
            'title' => $data['title'] ?? ($alarm ? "Alarm: {$alarm->message}" : ($crane ? "About {$crane->name}" : 'New conversation')),
        ]);

        return response()->json(['conversation' => $conversation], 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $conversation = $this->conversation($request, $id);

        return response()->json([
            'conversation' => $conversation,
            'messages' => $conversation->messages()->get()->map(fn (AiMessage $m) => ['role' => $m->role, 'content' => $this->forDisplay($m)])->values(),
        ]);
    }

    /** Server-sent events: text, tool_call, tool_result, confirm, error, done. */
    public function message(Request $request, string $id, AssistantService $assistant, TokenBudget $budget): StreamedResponse
    {
        $data = $request->validate(['message' => ['required', 'string', 'max:4000']]);
        $conversation = $this->conversation($request, $id);
        $user = $this->user($request);
        // Budget is checked before the stream starts, so an exhausted budget is a clean 429.
        $budget->ensureAvailable($user);

        return response()->eventStream(function () use ($assistant, $conversation, $user, $data) {
            try {
                foreach ($assistant->reply($conversation, $user, $data['message']) as $event) {
                    yield new StreamedEvent(event: $event['event'], data: $event['data']);
                }
            } catch (Throwable $e) {
                // Headers are already sent: report, and tell the client in-band.
                report($e);
                yield new StreamedEvent(event: 'error', data: ['message' => 'Something went wrong. Please try again.']);
            }
        }, headers: ['X-Accel-Buffering' => 'no'], endStreamWith: null);
    }

    private function conversation(Request $request, string $id): AiConversation
    {
        return AiConversation::query()->where('user_id', $this->user($request)->id)->findOrFail($id);
    }

    /**
     * What the UI shows: user text (without app context), assistant text, tool calls.
     *
     * @return list<array<string, mixed>>
     */
    private function forDisplay(AiMessage $m): array
    {
        $out = [];
        foreach ($m->content as $block) {
            $type = $block['type'] ?? null;
            if ($type === 'text' && ! str_starts_with((string) $block['text'], '<app_context>')) {
                $out[] = ['type' => 'text', 'text' => $block['text']];
            } elseif ($type === 'tool_use') {
                $out[] = ['type' => 'tool_call', 'id' => $block['id'], 'name' => $block['name'], 'input' => $block['input']];
            } elseif ($type === 'tool_result') {
                $out[] = ['type' => 'tool_result', 'id' => $block['tool_use_id'], 'ok' => ! ($block['is_error'] ?? false)];
            }
        }

        return $out;
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
