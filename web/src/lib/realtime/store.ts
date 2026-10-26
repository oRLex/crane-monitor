import type { Alarm, RealtimeEvent, TelemetryPoint } from "@/lib/domain";
import type { ConnectionState } from "./transport";

const MAX_HISTORY = 180;

export interface TelemetryState {
  connection: ConnectionState;
  latest: Record<string, TelemetryPoint>;
  history: Record<string, TelemetryPoint[]>;
  alarms: Alarm[];
  lastEventAt: number | null;
}

export const initialState: TelemetryState = {
  connection: "connecting",
  latest: {},
  history: {},
  alarms: [],
  lastEventAt: null,
};

/** Pure reducer: easy to unit-test and transport-agnostic. */
export function reduce(state: TelemetryState, event: RealtimeEvent, now = Date.now()): TelemetryState {
  switch (event.type) {
    case "snapshot": {
      const latest: Record<string, TelemetryPoint> = {};
      for (const p of event.points) latest[p.craneId] = p;
      return { ...state, latest, history: { ...event.history }, alarms: sortAlarms(event.alarms), lastEventAt: now };
    }
    case "telemetry": {
      const latest = { ...state.latest };
      const history = { ...state.history };
      for (const p of event.points) {
        latest[p.craneId] = p;
        const h = history[p.craneId] ?? [];
        history[p.craneId] = h.length >= MAX_HISTORY ? [...h.slice(h.length - MAX_HISTORY + 1), p] : [...h, p];
      }
      return { ...state, latest, history, lastEventAt: now };
    }
    case "alarm": {
      const others = state.alarms.filter((a) => a.id !== event.alarm.id);
      return { ...state, alarms: sortAlarms([event.alarm, ...others]), lastEventAt: now };
    }
  }
}

function sortAlarms(alarms: Alarm[]): Alarm[] {
  return [...alarms].sort((a, b) => b.raisedAt - a.raisedAt);
}

type Listener = () => void;

/** Tiny external store consumed via useSyncExternalStore. */
export class TelemetryStore {
  private state: TelemetryState = initialState;
  private readonly listeners = new Set<Listener>();

  getSnapshot = () => this.state;

  subscribe = (listener: Listener) => {
    this.listeners.add(listener);
    return () => this.listeners.delete(listener);
  };

  dispatch = (event: RealtimeEvent) => this.set(reduce(this.state, event));

  setConnection = (connection: ConnectionState) => {
    if (connection !== this.state.connection) this.set({ ...this.state, connection });
  };

  private set(next: TelemetryState) {
    this.state = next;
    for (const l of this.listeners) l();
  }
}
