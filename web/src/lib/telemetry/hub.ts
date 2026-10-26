import type { Alarm, Crane, RealtimeEvent, TelemetryPoint } from "@/lib/domain";
import { evaluate, reconcile } from "./alarms";
import { TelemetrySimulator } from "./simulator";

const HISTORY_SIZE = 180; // 3 minutes at 1 Hz
const ALARM_LOG_SIZE = 300;

type Listener = (event: RealtimeEvent) => void;

interface Subscription {
  craneIds: ReadonlySet<string>;
  listener: Listener;
}

/**
 * In-process telemetry hub: the stand-in for IoT Hub -> Azure SignalR in production.
 * Ticks only while someone is subscribed, keeps a short history per crane for charts,
 * runs alarm rules and fans events out to subscribers, filtered by the crane ids each
 * subscriber is allowed to see (RBAC is applied by the caller when subscribing).
 */
export class TelemetryHub {
  private readonly simulator: TelemetrySimulator;
  private readonly history = new Map<string, TelemetryPoint[]>();
  private alarms: Alarm[] = [];
  private readonly subs = new Set<Subscription>();
  private timer: ReturnType<typeof setInterval> | null = null;
  private seq = 0;

  constructor(
    cranes: readonly Crane[],
    private readonly intervalMs = 1000,
    seed?: number,
  ) {
    this.simulator = new TelemetrySimulator(cranes, { seed });
    // Pre-fill history so charts are not empty on first load.
    const now = Date.now();
    for (let i = 60; i > 0; i--) this.ingest(this.simulator.tick(now - i * intervalMs), false);
  }

  subscribe(craneIds: Iterable<string>, listener: Listener): () => void {
    const sub: Subscription = { craneIds: new Set(craneIds), listener };
    this.subs.add(sub);
    listener(this.snapshot(sub.craneIds));
    this.ensureRunning();
    return () => {
      this.subs.delete(sub);
      if (this.subs.size === 0) this.stop();
    };
  }

  snapshot(craneIds: ReadonlySet<string>): Extract<RealtimeEvent, { type: "snapshot" }> {
    const history: Record<string, TelemetryPoint[]> = {};
    const points: TelemetryPoint[] = [];
    for (const id of craneIds) {
      const h = this.history.get(id) ?? [];
      history[id] = h;
      const last = h.at(-1);
      if (last) points.push(last);
    }
    return { type: "snapshot", points, history, alarms: this.alarms.filter((a) => craneIds.has(a.craneId)) };
  }

  listAlarms(craneIds: ReadonlySet<string>): Alarm[] {
    return this.alarms.filter((a) => craneIds.has(a.craneId));
  }

  getAlarm(id: string): Alarm | undefined {
    return this.alarms.find((a) => a.id === id);
  }

  acknowledge(id: string, by: string, ts = Date.now()): Alarm | null {
    const alarm = this.getAlarm(id);
    if (!alarm || alarm.acknowledgedAt !== null) return alarm ?? null;
    const updated: Alarm = { ...alarm, acknowledgedAt: ts, acknowledgedBy: by };
    this.upsertAlarm(updated);
    this.emit({ type: "alarm", alarm: updated }, updated.craneId);
    return updated;
  }

  /** Visible for tests. */
  step(ts = Date.now()) {
    this.ingest(this.simulator.tick(ts), true);
  }

  private ensureRunning() {
    if (this.timer) return;
    this.timer = setInterval(() => this.step(), this.intervalMs);
    // Do not keep the Node process alive just for the simulator.
    (this.timer as { unref?: () => void }).unref?.();
  }

  private stop() {
    if (this.timer) clearInterval(this.timer);
    this.timer = null;
  }

  private ingest(points: TelemetryPoint[], broadcast: boolean) {
    const changedAlarms: Alarm[] = [];
    for (const p of points) {
      const h = this.history.get(p.craneId) ?? [];
      h.push(p);
      if (h.length > HISTORY_SIZE) h.splice(0, h.length - HISTORY_SIZE);
      this.history.set(p.craneId, h);

      const changed = reconcile(this.alarms, evaluate(p), p.craneId, p.ts, () => `al-${p.ts}-${++this.seq}`);
      for (const alarm of changed) this.upsertAlarm(alarm);
      changedAlarms.push(...changed);
    }
    if (!broadcast) return;
    for (const sub of this.subs) {
      const visible = points.filter((p) => sub.craneIds.has(p.craneId));
      if (visible.length) sub.listener({ type: "telemetry", points: visible });
      for (const alarm of changedAlarms) if (sub.craneIds.has(alarm.craneId)) sub.listener({ type: "alarm", alarm });
    }
  }

  private upsertAlarm(alarm: Alarm) {
    const idx = this.alarms.findIndex((a) => a.id === alarm.id);
    if (idx >= 0) this.alarms[idx] = alarm;
    else this.alarms.unshift(alarm);
    if (this.alarms.length > ALARM_LOG_SIZE) {
      // Drop the oldest cleared alarms first; never drop open ones.
      this.alarms = this.alarms.filter((a, i) => i < ALARM_LOG_SIZE || a.clearedAt === null);
    }
  }

  private emit(event: RealtimeEvent, craneId: string) {
    for (const sub of this.subs) if (sub.craneIds.has(craneId)) sub.listener(event);
  }
}
