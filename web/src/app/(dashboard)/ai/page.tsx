import { api } from "@/lib/api/server";

export const metadata = { title: "AI usage" };

interface Usage {
  model: string;
  mock: boolean;
  since: string;
  totals: { calls: number; cost_usd: number; cache_hit_rate: number | null; p50_ms: number | null; p95_ms: number | null };
  stop_reasons: Record<string, number>;
  by_day: { day: string; feature: string; calls: number; input: number; output: number; cache_read: number; cost: string }[];
  by_tenant: { tenant: string; calls: number; tokens: number; cost: string }[];
  budget: { daily_limit: number; used_today: number };
}

/** Post-launch view: what the AI features cost, how fast they are and whether caching works. */
export default async function AiUsagePage() {
  const u = await api<Usage>("/ai/usage?days=7");
  const tiles = [
    ["Calls (7 days)", u.totals.calls.toLocaleString()],
    ["Cost", `$${u.totals.cost_usd.toFixed(4)}`],
    ["Prompt cache hit rate", u.totals.cache_hit_rate === null ? "—" : `${Math.round(u.totals.cache_hit_rate * 100)}%`],
    ["Latency p50 / p95", u.totals.p50_ms === null ? "—" : `${(u.totals.p50_ms / 1000).toFixed(1)}s / ${((u.totals.p95_ms ?? 0) / 1000).toFixed(1)}s`],
  ];

  return (
    <div className="space-y-6">
      <header>
        <h1 className="text-xl font-semibold">AI usage</h1>
        <p className="text-sm text-muted">
          Model <span className="font-mono">{u.model}</span>
          {u.mock && " — mock mode (no API key configured), costs are zero"}. Every LLM call is recorded in the usage ledger.
        </p>
      </header>
      <dl className="grid grid-cols-2 gap-3 lg:grid-cols-4">
        {tiles.map(([k, v]) => (
          <div key={k} className="card px-4 py-3">
            <dt className="text-xs text-muted">{k}</dt>
            <dd className="tabular mt-1 font-mono text-xl">{v}</dd>
          </div>
        ))}
      </dl>
      <div className="grid gap-6 lg:grid-cols-2">
        <Table
          title="By day and feature"
          head={["Day", "Feature", "Calls", "Input", "Cached", "Output", "Cost"]}
          rows={u.by_day.map((r) => [r.day, r.feature, r.calls, r.input, r.cache_read, r.output, `$${Number(r.cost).toFixed(4)}`])}
        />
        <div className="space-y-6">
          <Table
            title="By tenant"
            head={["Tenant", "Calls", "Tokens", "Cost"]}
            rows={u.by_tenant.map((r) => [r.tenant, r.calls, Number(r.tokens).toLocaleString(), `$${Number(r.cost).toFixed(4)}`])}
          />
          <Table title="Stop reasons" head={["Reason", "Count"]} rows={Object.entries(u.stop_reasons).map(([k, v]) => [k, v])} />
          <p className="text-xs text-faint">
            Daily budget per organisation: {u.budget.daily_limit.toLocaleString()} tokens (your bucket used today: {u.budget.used_today.toLocaleString()}).
          </p>
        </div>
      </div>
    </div>
  );
}

function Table({ title, head, rows }: { title: string; head: string[]; rows: (string | number)[][] }) {
  return (
    <section className="card overflow-x-auto">
      <h2 className="border-b border-line px-4 py-3 font-semibold">{title}</h2>
      <table className="tabular w-full text-sm">
        <thead className="text-left text-xs text-muted">
          <tr>
            {head.map((h) => (
              <th key={h} className="px-4 py-2 font-normal">
                {h}
              </th>
            ))}
          </tr>
        </thead>
        <tbody className="divide-y divide-line">
          {rows.length === 0 && (
            <tr>
              <td colSpan={head.length} className="px-4 py-6 text-center text-faint">
                No data yet
              </td>
            </tr>
          )}
          {rows.map((r, i) => (
            <tr key={i}>
              {r.map((c, j) => (
                <td key={j} className="px-4 py-2 font-mono text-xs">
                  {c}
                </td>
              ))}
            </tr>
          ))}
        </tbody>
      </table>
    </section>
  );
}
