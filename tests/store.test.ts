import { describe, expect, it } from "vitest";
import type { Alarm, TelemetryPoint } from "@/lib/domain";
import { initialState, reduce } from "@/lib/realtime/store";

const pt = (craneId: string, ts: number): TelemetryPoint => ({
  craneId,
  ts,
  loadT: 1,
  radiusM: 10,
  loadMomentPct: 10,
  windMs: 3,
  slewDeg: 0,
  hookHeightM: 10,
  online: true,
});

const alarm = (id: string, raisedAt: number): Alarm => ({
  id,
  craneId: "c-1",
  kind: "wind",
  severity: "warning",
  message: "",
  raisedAt,
  acknowledgedAt: null,
  acknowledgedBy: null,
  clearedAt: null,
});

describe("telemetry reducer", () => {
  it("applies snapshot then streams points", () => {
    let s = reduce(initialState, { type: "snapshot", points: [pt("c-1", 1)], history: { "c-1": [pt("c-1", 1)] }, alarms: [] });
    s = reduce(s, { type: "telemetry", points: [pt("c-1", 2), pt("c-2", 2)] });
    expect(s.latest["c-1"]?.ts).toBe(2);
    expect(s.history["c-1"]).toHaveLength(2);
    expect(s.history["c-2"]).toHaveLength(1);
  });

  it("caps history length", () => {
    let s = initialState;
    for (let i = 0; i < 500; i++) s = reduce(s, { type: "telemetry", points: [pt("c-1", i)] });
    expect(s.history["c-1"]).toHaveLength(180);
    expect(s.history["c-1"]!.at(-1)!.ts).toBe(499);
  });

  it("upserts alarms newest first", () => {
    let s = reduce(initialState, { type: "alarm", alarm: alarm("a", 1) });
    s = reduce(s, { type: "alarm", alarm: alarm("b", 2) });
    s = reduce(s, { type: "alarm", alarm: { ...alarm("a", 1), acknowledgedAt: 3, acknowledgedBy: "op" } });
    expect(s.alarms.map((a) => a.id)).toEqual(["b", "a"]);
    expect(s.alarms[1]!.acknowledgedBy).toBe("op");
  });

  it("does not mutate previous state", () => {
    const s1 = reduce(initialState, { type: "telemetry", points: [pt("c-1", 1)] });
    const s2 = reduce(s1, { type: "telemetry", points: [pt("c-1", 2)] });
    expect(s1.history["c-1"]).toHaveLength(1);
    expect(s2).not.toBe(s1);
  });
});
