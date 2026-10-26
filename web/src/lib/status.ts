import type { Alarm, CraneStatus, TelemetryPoint } from "@/lib/domain";

/** Display thresholds; the API's AlarmEngine is the source of truth for alarms. */
export const THRESHOLDS = {
  wind: { warning: 14, critical: 20 },
  loadMoment: { warning: 90, critical: 100 },
} as const;

/** offline > alarm > operating > idle — same rule as AlarmEngine::status on the API. */
export function deriveStatus(point: TelemetryPoint | undefined, alarms: readonly Alarm[]): CraneStatus {
  if (!point || !point.online) return "offline";
  if (alarms.some((a) => a.craneId === point.craneId && a.clearedAt === null)) return "alarm";
  return point.loadT > 0.2 ? "operating" : "idle";
}
