"use client";

import { useCallback, useEffect, useState } from "react";
import type { ShiftReport } from "@/lib/domain";

const RISK: Record<string, string> = { low: "text-ok", medium: "text-warn", high: "text-crit" };

/** Refreshes when a "report" WebSocket event arrives, with a slow poll as a fallback. */
export function ReportList({ initial }: { initial: ShiftReport[] }) {
  const [reports, setReports] = useState(initial);
  const [open, setOpen] = useState<string | null>(initial[0]?.id ?? null);

  useEffect(() => setReports(initial), [initial]);

  // A newly requested report (newest first) opens by itself.
  const newest = reports[0]?.id;
  useEffect(() => setOpen((o) => (newest && o !== newest ? newest : o)), [newest]);

  const refresh = useCallback(async () => {
    const res = await fetch("/api/backend/reports", { headers: { Accept: "application/json" } });
    if (res.ok) setReports((await res.json()).reports);
  }, []);

  useEffect(() => {
    const pending = reports.some((r) => r.status === "queued" || r.status === "running");
    window.addEventListener("cranewatch:report", refresh);
    const timer = pending ? setInterval(refresh, 4000) : undefined;
    return () => {
      window.removeEventListener("cranewatch:report", refresh);
      clearInterval(timer);
    };
  }, [reports, refresh]);

  if (reports.length === 0) return <p className="card px-4 py-8 text-center text-sm text-faint">No reports yet.</p>;

  return (
    <ul className="space-y-3">
      {reports.map((r) => (
        <li key={r.id} className="card">
          <button type="button" onClick={() => setOpen(open === r.id ? null : r.id)} className="flex w-full items-center gap-3 px-4 py-3 text-left">
            <span className="font-medium">{r.craneName}</span>
            <span className="tabular text-xs text-faint">
              {fmt(r.periodStart)} – {fmt(r.periodEnd)}
            </span>
            <span className="ml-auto text-xs">
              {r.status === "ready" && r.report ? (
                <span className={RISK[r.report.risk_level]}>{r.report.risk_level} risk</span>
              ) : (
                <span className={r.status === "failed" ? "text-crit" : "animate-pulse-dot text-warn"}>{r.status}</span>
              )}
            </span>
          </button>
          {open === r.id && <ReportBody report={r} />}
        </li>
      ))}
    </ul>
  );
}

function ReportBody({ report: r }: { report: ShiftReport }) {
  if (r.status === "failed") return <p className="border-t border-line px-4 py-3 text-sm text-crit">{r.error}</p>;
  if (!r.report) return <p className="border-t border-line px-4 py-3 text-sm text-faint">Generating…</p>;
  const totals = (r.facts?.totals ?? {}) as Record<string, number | null>;
  return (
    <div className="space-y-4 border-t border-line px-4 py-4 text-sm">
      <p>{r.report.summary}</p>
      <dl className="grid grid-cols-2 gap-2 sm:grid-cols-4">
        {[
          ["Lifts", totals.lifts],
          ["Operating", totals.operating_min != null ? `${totals.operating_min} min` : null],
          ["Peak moment", totals.peak_load_moment_pct != null ? `${totals.peak_load_moment_pct}%` : null],
          ["Peak wind", totals.peak_wind_ms != null ? `${totals.peak_wind_ms} m/s` : null],
        ].map(([k, v]) => (
          <div key={String(k)} className="rounded-lg bg-surface-2 px-3 py-2">
            <dt className="text-xs text-muted">{k}</dt>
            <dd className="tabular font-mono">{v ?? "—"}</dd>
          </div>
        ))}
      </dl>
      {r.report.highlights.length > 0 && <List title="Highlights" items={r.report.highlights} />}
      {r.report.safety_events.length > 0 && (
        <div>
          <h3 className="mb-1 text-xs text-muted">Safety events</h3>
          <ul className="space-y-1">
            {r.report.safety_events.map((e, i) => (
              <li key={i} className="flex gap-2">
                <span className="tabular font-mono text-xs text-faint">{fmt(e.time)}</span>
                <span className={e.severity === "critical" ? "text-crit" : "text-warn"}>{e.kind}</span>
                <span>{e.description}</span>
              </li>
            ))}
          </ul>
        </div>
      )}
      <List title="Recommendations" items={r.report.recommendations} />
      <p className="text-[11px] text-faint">Model: {r.model}</p>
    </div>
  );
}

function List({ title, items }: { title: string; items: string[] }) {
  return (
    <div>
      <h3 className="mb-1 text-xs text-muted">{title}</h3>
      <ul className="list-disc space-y-1 pl-5">
        {items.map((h, i) => (
          <li key={i}>{h}</li>
        ))}
      </ul>
    </div>
  );
}

const fmt = (iso: string) => new Date(iso).toLocaleString([], { month: "short", day: "numeric", hour: "2-digit", minute: "2-digit" });
