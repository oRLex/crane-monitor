import type { Alarm, AlarmKind, AlarmSeverity, CraneStatus, TelemetryPoint } from "@/lib/domain";

export const THRESHOLDS = {
  wind: { warning: 14, critical: 20 }, // m/s; most tower cranes must stop at ~20 m/s
  loadMoment: { warning: 90, critical: 100 }, // % of rated moment
} as const;

export interface Condition {
  kind: AlarmKind;
  severity: AlarmSeverity;
  message: string;
}

/** Pure rule evaluation for one telemetry sample. */
export function evaluate(point: TelemetryPoint): Condition[] {
  if (!point.online) return [{ kind: "offline", severity: "critical", message: "Telemetry link lost" }];

  const out: Condition[] = [];
  const { wind, loadMoment } = THRESHOLDS;
  if (point.windMs >= wind.warning) {
    const severity = point.windMs >= wind.critical ? "critical" : "warning";
    out.push({ kind: "wind", severity, message: `Wind ${point.windMs} m/s (limit ${wind.critical} m/s)` });
  }
  if (point.loadMomentPct >= loadMoment.warning) {
    const severity = point.loadMomentPct >= loadMoment.critical ? "critical" : "warning";
    out.push({
      kind: "overload",
      severity,
      message: `Load moment ${point.loadMomentPct}% (${point.loadT} t at ${point.radiusM} m)`,
    });
  }
  return out;
}

const rank: Record<AlarmSeverity, number> = { warning: 1, critical: 2 };

/**
 * Reconciles active alarms for a crane against the latest conditions.
 * - new condition            -> raise alarm
 * - worse severity           -> raise a new (escalated) alarm, clear the old one
 * - condition gone           -> clear alarm
 * Returns alarms that changed so they can be broadcast.
 */
export function reconcile(
  active: readonly Alarm[],
  conditions: readonly Condition[],
  craneId: string,
  ts: number,
  nextId: () => string,
): Alarm[] {
  const changed: Alarm[] = [];
  const open = active.filter((a) => a.craneId === craneId && a.clearedAt === null);

  for (const alarm of open) {
    const cond = conditions.find((c) => c.kind === alarm.kind);
    if (!cond || rank[cond.severity] > rank[alarm.severity]) {
      changed.push({ ...alarm, clearedAt: ts });
    }
  }
  for (const cond of conditions) {
    const existing = open.find((a) => a.kind === cond.kind);
    if (!existing || rank[cond.severity] > rank[existing.severity]) {
      changed.push({
        id: nextId(),
        craneId,
        kind: cond.kind,
        severity: cond.severity,
        message: cond.message,
        raisedAt: ts,
        acknowledgedAt: null,
        acknowledgedBy: null,
        clearedAt: null,
      });
    }
  }
  return changed;
}

export function deriveStatus(point: TelemetryPoint | undefined, alarms: readonly Alarm[]): CraneStatus {
  if (!point || !point.online) return "offline";
  if (alarms.some((a) => a.craneId === point.craneId && a.clearedAt === null)) return "alarm";
  return point.loadT > 0.2 ? "operating" : "idle";
}
