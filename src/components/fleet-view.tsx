"use client";

import Link from "next/link";
import { useMemo, useState } from "react";
import type { Crane, CraneStatus, Site } from "@/lib/domain";
import { deriveStatus, THRESHOLDS } from "@/lib/telemetry/alarms";
import { AlarmList } from "./alarm-list";
import { useTelemetry } from "./telemetry-provider";
import { MomentBar, SlewGauge, Sparkline } from "./ui/charts";
import { ConnectionDot, levelColor, StatusPill } from "./ui/status";

const FILTERS: (CraneStatus | "all")[] = ["all", "operating", "idle", "alarm", "offline"];

export function FleetView({
  cranes,
  sites,
  canAck,
  userName,
}: {
  cranes: Crane[];
  sites: Site[];
  canAck: boolean;
  userName: string;
}) {
  const latest = useTelemetry((s) => s.latest);
  const history = useTelemetry((s) => s.history);
  const alarms = useTelemetry((s) => s.alarms);
  const connection = useTelemetry((s) => s.connection);
  const [filter, setFilter] = useState<CraneStatus | "all">("all");
  const [siteId, setSiteId] = useState<string>("all");

  const statuses = useMemo(
    () => Object.fromEntries(cranes.map((c) => [c.id, deriveStatus(latest[c.id], alarms)])) as Record<string, CraneStatus>,
    [cranes, latest, alarms],
  );
  const counts = useMemo(() => {
    const out: Record<CraneStatus, number> = { operating: 0, idle: 0, alarm: 0, offline: 0 };
    for (const s of Object.values(statuses)) out[s]++;
    return out;
  }, [statuses]);

  const siteById = useMemo(() => new Map(sites.map((s) => [s.id, s])), [sites]);
  const craneNames = useMemo(() => Object.fromEntries(cranes.map((c) => [c.id, c.name])), [cranes]);
  const activeAlarms = useMemo(() => alarms.filter((a) => a.clearedAt === null), [alarms]);
  const shown = cranes.filter(
    (c) => (filter === "all" || statuses[c.id] === filter) && (siteId === "all" || c.siteId === siteId),
  );
  const hasData = Object.keys(latest).length > 0;

  return (
    <div className="grid gap-6 xl:grid-cols-[1fr_360px]">
      <section aria-labelledby="fleet-heading" className="min-w-0">
        <div className="mb-4 flex flex-wrap items-end justify-between gap-3">
          <div>
            <h1 id="fleet-heading" className="text-xl font-semibold">
              Fleet overview
            </h1>
            <p className="text-sm text-muted">
              {cranes.length} {cranes.length === 1 ? "crane" : "cranes"} · {sites.length}{" "}
              {sites.length === 1 ? "site" : "sites"}
            </p>
          </div>
          <ConnectionDot state={connection} />
        </div>

        <dl className="mb-5 grid grid-cols-2 gap-3 sm:grid-cols-4">
          {(["operating", "idle", "alarm", "offline"] as const).map((s) => (
            <button
              key={s}
              type="button"
              onClick={() => setFilter(filter === s ? "all" : s)}
              aria-pressed={filter === s}
              className={`card px-4 py-3 text-left transition hover:border-faint ${filter === s ? "border-crane!" : ""}`}
            >
              <dt>
                <StatusPill status={s} />
              </dt>
              <dd className="tabular mt-1 font-mono text-2xl">{hasData ? counts[s] : "—"}</dd>
            </button>
          ))}
        </dl>

        <div className="mb-4 flex flex-wrap items-center gap-2 text-sm">
          <label className="sr-only" htmlFor="site">
            Site
          </label>
          <select
            id="site"
            value={siteId}
            onChange={(e) => setSiteId(e.target.value)}
            className="rounded-md border border-line bg-surface px-2.5 py-1.5 text-ink"
          >
            <option value="all">All sites</option>
            {sites.map((s) => (
              <option key={s.id} value={s.id}>
                {s.name} — {s.city}
              </option>
            ))}
          </select>
          <div className="flex gap-1" role="group" aria-label="Status filter">
            {FILTERS.map((f) => (
              <button
                key={f}
                type="button"
                onClick={() => setFilter(f)}
                aria-pressed={filter === f}
                className={`rounded-md px-2.5 py-1.5 capitalize transition ${
                  filter === f ? "bg-surface-2 text-ink" : "text-muted hover:text-ink"
                }`}
              >
                {f}
              </button>
            ))}
          </div>
        </div>

        {shown.length === 0 ? (
          <p className="card px-4 py-10 text-center text-sm text-faint">No cranes match the filter.</p>
        ) : (
          <ul className="grid gap-4 sm:grid-cols-2 2xl:grid-cols-3">
            {shown.map((crane) => {
              const p = latest[crane.id];
              const status = statuses[crane.id] ?? "offline";
              const site = siteById.get(crane.siteId);
              return (
                <li key={crane.id}>
                  <Link
                    href={`/cranes/${crane.id}`}
                    className={`card group block p-4 transition hover:border-faint ${
                      status === "alarm" ? "border-crit/50!" : ""
                    }`}
                  >
                    <div className="mb-3 flex items-start justify-between gap-3">
                      <div className="min-w-0">
                        <h2 className="font-semibold group-hover:text-crane">{crane.name}</h2>
                        <p className="truncate text-xs text-muted">
                          {crane.model} · {site?.name}
                        </p>
                      </div>
                      <StatusPill status={status} />
                    </div>

                    <div className="flex items-center gap-4">
                      <SlewGauge
                        slewDeg={p?.slewDeg ?? 0}
                        radiusM={p?.radiusM ?? 0}
                        jibLengthM={crane.jibLengthM}
                        className={`size-16 shrink-0 ${p?.online ? "" : "opacity-30"}`}
                      />
                      <dl className="tabular grid flex-1 grid-cols-2 gap-x-3 gap-y-1.5 font-mono text-sm">
                        <Metric label="Load" value={p ? `${p.loadT.toFixed(1)} t` : "—"} />
                        <Metric label="Radius" value={p ? `${p.radiusM.toFixed(0)} m` : "—"} />
                        <Metric
                          label="Wind"
                          value={p ? `${p.windMs.toFixed(1)} m/s` : "—"}
                          color={p ? levelColor(p.windMs, THRESHOLDS.wind.warning, THRESHOLDS.wind.critical) : undefined}
                        />
                        <Metric label="Hook" value={p ? `${p.hookHeightM.toFixed(0)} m` : "—"} />
                      </dl>
                    </div>

                    <div className="mt-4">
                      <div className="mb-1 flex justify-between text-xs">
                        <span className="text-muted">Load moment</span>
                        <span className="tabular font-mono">{p ? `${p.loadMomentPct.toFixed(0)}%` : "—"}</span>
                      </div>
                      <MomentBar pct={p?.loadMomentPct ?? 0} />
                      <Sparkline
                        values={(history[crane.id] ?? []).slice(-60).map((h) => h.loadMomentPct)}
                        max={110}
                        className="mt-2 h-8 w-full"
                      />
                    </div>
                  </Link>
                </li>
              );
            })}
          </ul>
        )}
      </section>

      <aside aria-labelledby="alarms-heading" className="card h-fit xl:sticky xl:top-20">
        <div className="flex items-center justify-between border-b border-line px-4 py-3">
          <h2 id="alarms-heading" className="font-semibold">
            Active alarms
          </h2>
          <span className="tabular rounded-full bg-surface-2 px-2 py-0.5 font-mono text-xs">{activeAlarms.length}</span>
        </div>
        <div className="max-h-[70vh] overflow-y-auto">
          <AlarmList
            alarms={activeAlarms}
            canAck={canAck}
            craneNames={craneNames}
            userName={userName}
            emptyText="All clear — no active alarms"
          />
        </div>
      </aside>
    </div>
  );
}

function Metric({ label, value, color }: { label: string; value: string; color?: string }) {
  return (
    <div>
      <dt className="font-sans text-[11px] text-faint">{label}</dt>
      <dd style={color ? { color } : undefined}>{value}</dd>
    </div>
  );
}
