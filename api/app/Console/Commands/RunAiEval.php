<?php

namespace App\Console\Commands;

use App\Ai\Assistant\AssistantService;
use App\Ai\Knowledge\KnowledgeSearch;
use App\Ai\Llm\LlmClient;
use App\Models\AiConversation;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Small golden-set eval, run before changing prompts, tools, chunking or models.
 *
 * - retrieval: recall@k and MRR of the expected document (cheap, no LLM)
 * - assistant: expected tool calls, citations and forbidden content per scenario.
 *   With a real API key this spends money (a few cents); the result is printed with cost.
 */
class RunAiEval extends Command
{
    protected $signature = 'ai:eval {--suite=all : retrieval|assistant|all} {--k=3}';

    protected $description = 'Run the AI golden-set evaluation';

    public function handle(KnowledgeSearch $search, AssistantService $assistant, LlmClient $llm): int
    {
        $suite = (string) $this->option('suite');
        $results = [];
        if (in_array($suite, ['retrieval', 'all'], true)) {
            $results[] = $this->retrieval($search);
        }
        if (in_array($suite, ['assistant', 'all'], true)) {
            $results[] = $this->assistant($assistant, $llm);
        }

        return in_array(false, $results, true) ? self::FAILURE : self::SUCCESS;
    }

    private function retrieval(KnowledgeSearch $search): bool
    {
        $k = (int) $this->option('k');
        $cases = json_decode((string) file_get_contents(resource_path('evals/retrieval.json')), true);
        $admin = User::query()->where('email', 'admin@demo.io')->firstOrFail();
        $rows = [];
        $hits = 0;
        $rr = 0.0;
        foreach ($cases as $case) {
            $titles = array_map(fn ($h) => $h->documentTitle, $search->search($case['query'], $admin, 8));
            $rank = array_search($case['expect'], array_values(array_unique($titles)), true);
            $hit = $rank !== false && $rank < $k;
            $hits += $hit ? 1 : 0;
            $rr += $rank === false ? 0 : 1 / ($rank + 1);
            $rows[] = [$hit ? '✓' : '✗', $case['query'], $rank === false ? '-' : $rank + 1];
        }
        $this->table(['', 'query', 'rank'], $rows);
        $recall = $hits / count($cases);
        $this->info(sprintf('retrieval: recall@%d = %.2f, MRR = %.2f (%d cases)', $k, $recall, $rr / count($cases), count($cases)));

        return $recall >= 0.8;
    }

    private function assistant(AssistantService $assistant, LlmClient $llm): bool
    {
        $cases = json_decode((string) file_get_contents(resource_path('evals/assistant.json')), true);
        $since = now();
        $rows = [];
        $passed = 0;
        foreach ($cases as $case) {
            $user = User::query()->where('email', $case['as'])->firstOrFail();
            $conversation = AiConversation::query()->create(['user_id' => $user->id, 'tenant_id' => $user->tenant_id, 'title' => '[eval] '.$case['message']]);
            $tools = [];
            $text = '';
            foreach ($assistant->reply($conversation, $user, $case['message']) as $e) {
                if ($e['event'] === 'tool_call') {
                    $tools[] = $e['data']['name'];
                } elseif ($e['event'] === 'text') {
                    $text .= $e['data']['text']."\n";
                }
            }
            $problems = [];
            foreach ($case['tools'] ?? [] as $t) {
                if (! in_array($t, $tools, true)) {
                    $problems[] = "missing {$t}";
                }
            }
            foreach ($case['forbid_tools'] ?? [] as $t) {
                if (in_array($t, $tools, true)) {
                    $problems[] = "called {$t}";
                }
            }
            if (($case['cite'] ?? false) && ! preg_match('/\[D\d+§\d+\]/u', $text)) {
                $problems[] = 'no citation';
            }
            foreach ($case['forbid'] ?? [] as $phrase) {
                if (stripos($text, $phrase) !== false) {
                    $problems[] = "said \"{$phrase}\"";
                }
            }
            $passed += $problems === [] ? 1 : 0;
            $rows[] = [$problems === [] ? '✓' : '✗', $case['as'], mb_strimwidth($case['message'], 0, 50, '…'), implode(', ', $tools), implode('; ', $problems)];
            $conversation->delete();
        }
        $this->table(['', 'as', 'message', 'tools', 'problems'], $rows);
        $cost = (float) DB::table('ai_usage')->where('created_at', '>=', $since)->sum('cost_usd');
        $this->info(sprintf('assistant (%s): %d/%d passed, cost $%.4f', $llm->isMock() ? 'mock' : $llm->model(), $passed, count($cases), $cost));

        return $passed === count($cases);
    }
}
