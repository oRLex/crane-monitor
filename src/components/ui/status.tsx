import type { AlarmSeverity, CraneStatus } from "@/lib/domain";
import type { ConnectionState } from "@/lib/realtime/transport";

const STATUS: Record<CraneStatus, { label: string; dot: string; text: string }> = {
  operating: { label: "Operating", dot: "bg-ok", text: "text-ok" },
  idle: { label: "Idle", dot: "bg-muted", text: "text-muted" },
  alarm: { label: "Alarm", dot: "bg-crit animate-pulse-dot", text: "text-crit" },
  offline: { label: "Offline", dot: "bg-off", text: "text-off" },
};

export function StatusPill({ status }: { status: CraneStatus }) {
  const s = STATUS[status];
  return (
    <span className={`inline-flex items-center gap-1.5 text-xs font-medium ${s.text}`}>
      <span className={`size-2 rounded-full ${s.dot}`} aria-hidden />
      {s.label}
    </span>
  );
}

export function SeverityBadge({ severity }: { severity: AlarmSeverity }) {
  const cls =
    severity === "critical" ? "bg-crit/15 text-crit ring-crit/30" : "bg-warn/15 text-warn ring-warn/30";
  return (
    <span className={`rounded px-1.5 py-0.5 text-[11px] font-semibold uppercase tracking-wide ring-1 ${cls}`}>
      {severity}
    </span>
  );
}

const CONN: Record<ConnectionState, { label: string; dot: string }> = {
  connecting: { label: "Connecting…", dot: "bg-warn animate-pulse-dot" },
  open: { label: "Live", dot: "bg-ok animate-pulse-dot" },
  reconnecting: { label: "Reconnecting…", dot: "bg-warn animate-pulse-dot" },
  closed: { label: "Disconnected", dot: "bg-crit" },
};

export function ConnectionDot({ state }: { state: ConnectionState }) {
  const c = CONN[state];
  return (
    <span className="inline-flex items-center gap-2 text-xs text-muted" role="status" aria-live="polite">
      <span className={`size-2 rounded-full ${c.dot}`} aria-hidden />
      {c.label}
    </span>
  );
}

/** Colour ramp for a value against warning/critical thresholds. */
export function levelColor(value: number, warn: number, crit: number): string {
  if (value >= crit) return "var(--color-crit)";
  if (value >= warn) return "var(--color-warn)";
  return "var(--color-ok)";
}
