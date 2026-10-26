// Wire types of the Laravel API, shared by server and client components.

export type Role = "admin" | "operator" | "customer_manager" | "customer_viewer";

export type Scope =
  | "cranes:read"
  | "telemetry:read"
  | "video:read"
  | "alarms:read"
  | "alarms:ack"
  | "docs:read"
  | "docs:write"
  | "reports:write"
  | "ai:use"
  | "ai:admin"
  | "tenants:all";

export interface Profile {
  id: number;
  name: string;
  email: string;
  role: Role;
  roleLabel: string;
  tenantId: string | null;
  tenantName: string | null;
  scopes: Scope[];
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
  /** null unless the user has video:read (enforced by the API). */
  streamUrl: string | null;
  siteId: string;
  tenantId: string;
  site?: Site;
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

/** Messages applied to the real-time store (from the snapshot endpoint and Reverb events). */
export type RealtimeEvent =
  | { type: "snapshot"; points: TelemetryPoint[]; history: Record<string, TelemetryPoint[]>; alarms: Alarm[] }
  | { type: "telemetry"; points: TelemetryPoint[] }
  | { type: "alarm"; alarm: Alarm };

export interface ShiftReport {
  id: string;
  craneId: string;
  craneName: string;
  periodStart: string;
  periodEnd: string;
  status: "queued" | "running" | "ready" | "failed";
  report: {
    summary: string;
    risk_level: "low" | "medium" | "high";
    highlights: string[];
    safety_events: { time: string; kind: AlarmKind; severity: AlarmSeverity; description: string }[];
    recommendations: string[];
  } | null;
  facts: Record<string, unknown> | null;
  error: string | null;
  model: string | null;
  createdAt: string;
}

export interface KnowledgeDocument {
  id: number;
  tenant_id: string | null;
  title: string;
  source: string | null;
  status: "pending" | "indexed" | "failed";
  error: string | null;
  chunk_count: number;
  embedding_model: string | null;
  updated_at: string;
}

export interface Passage {
  citation: string;
  document_id: number;
  document: string;
  section: string;
  text: string;
  score: number;
  source: string;
}

export const can = (profile: Pick<Profile, "scopes"> | null | undefined, scope: Scope) => !!profile?.scopes.includes(scope);
