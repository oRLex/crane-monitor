"use client";

import Link from "next/link";
import { useOptimistic, useState, useTransition } from "react";
import { acknowledgeAlarm } from "@/lib/actions";
import type { Alarm } from "@/lib/domain";
import { SeverityBadge } from "./ui/status";

type Optimistic = { id: string; by: string };

export function AlarmList({
  alarms,
  canAck,
  craneNames,
  userName,
  emptyText = "No alarms",
}: {
  alarms: Alarm[];
  canAck: boolean;
  craneNames?: Record<string, string>;
  userName: string;
  emptyText?: string;
}) {
  const [pending, startTransition] = useTransition();
  const [error, setError] = useState<string | null>(null);
  // React 19: show the ack instantly, roll back automatically if the action fails.
  const [optimistic, addOptimistic] = useOptimistic(alarms, (current, ack: Optimistic) =>
    current.map((a) => (a.id === ack.id ? { ...a, acknowledgedAt: Date.now(), acknowledgedBy: ack.by } : a)),
  );

  const ack = (id: string) =>
    startTransition(async () => {
      setError(null);
      addOptimistic({ id, by: userName });
      const res = await acknowledgeAlarm(id);
      if (!res.ok) setError(res.error);
    });

  if (optimistic.length === 0) return <p className="px-4 py-6 text-center text-sm text-faint">{emptyText}</p>;

  return (
    <div>
      {error && <p className="mx-4 mt-3 rounded-md bg-crit/10 px-3 py-2 text-sm text-crit">{error}</p>}
      <ul className="divide-y divide-line" aria-busy={pending}>
        {optimistic.map((a) => {
          const active = a.clearedAt === null;
          return (
            <li key={a.id} className={`flex items-start gap-3 px-4 py-3 ${active ? "" : "opacity-55"}`}>
              <div className="min-w-0 flex-1">
                <div className="mb-1 flex flex-wrap items-center gap-2">
                  <SeverityBadge severity={a.severity} />
                  {craneNames && (
                    <Link href={`/cranes/${a.craneId}`} className="text-sm font-medium hover:text-crane">
                      {craneNames[a.craneId] ?? a.craneId}
                    </Link>
                  )}
                  <span className="text-xs text-faint">{active ? "active" : "cleared"}</span>
                </div>
                <p className="truncate text-sm text-ink">{a.message}</p>
                <p className="tabular mt-0.5 font-mono text-[11px] text-faint">
                  {new Date(a.raisedAt).toLocaleTimeString()}
                  {a.acknowledgedAt && ` · ack by ${a.acknowledgedBy}`}
                </p>
              </div>
              {canAck && !a.acknowledgedAt && (
                <button
                  type="button"
                  onClick={() => ack(a.id)}
                  className="shrink-0 rounded-md border border-line px-2.5 py-1 text-xs text-ink transition hover:border-crane hover:text-crane"
                >
                  Acknowledge
                </button>
              )}
            </li>
          );
        })}
      </ul>
    </div>
  );
}
