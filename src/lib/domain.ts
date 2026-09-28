// Core domain types shared by server and client code.

export type Role = "admin" | "operator" | "customer_manager" | "customer_viewer";

export type Scope =
  | "cranes:read"
  | "telemetry:read"
  | "video:read"
  | "alarms:read"
  | "alarms:ack"
  | "tenants:all";

export interface Tenant {
  id: string;
  name: string;
}

export interface Site {
  id: string;
  name: string;
  city: string;
  tenantId: string;
}

export interface Crane {
  id: string;
  name: string;
  model: string;
  serialNumber: string;
  maxLoadT: number;
  jibLengthM: number;
  /** Rated load moment, tonne-metres. */
  ratedMomentTm: number;
  streamUrl: string | null;
  siteId: string;
  tenantId: string;
}

export interface User {
  id: string;
  email: string;
  name: string;
  role: Role;
  /** null for platform-wide roles. */
  tenantId: string | null;
}

export type CraneStatus = "operating" | "idle" | "alarm" | "offline";

export interface TelemetryPoint {
  craneId: string;
  /** Unix epoch, ms. */
  ts: number;
  loadT: number;
  radiusM: number;
  loadMomentPct: number;
  windMs: number;
  slewDeg: number;
  hookHeightM: number;
  online: boolean;
}

export type AlarmKind = "wind" | "overload" | "offline";
export type AlarmSeverity = "warning" | "critical";

export interface Alarm {
  id: string;
  craneId: string;
  kind: AlarmKind;
  severity: AlarmSeverity;
  message: string;
  raisedAt: number;
  acknowledgedAt: number | null;
  acknowledgedBy: string | null;
  /** Set when the triggering condition is no longer present. */
  clearedAt: number | null;
}

/** Messages pushed over the real-time channel (SSE or SignalR). */
export type RealtimeEvent =
  | { type: "snapshot"; points: TelemetryPoint[]; history: Record<string, TelemetryPoint[]>; alarms: Alarm[] }
  | { type: "telemetry"; points: TelemetryPoint[] }
  | { type: "alarm"; alarm: Alarm };
