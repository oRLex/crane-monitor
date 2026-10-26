import { describe, expect, it } from "vitest";
import type { RealtimeEvent } from "@/lib/domain";
import { cranes } from "@/lib/data/seed-data";
import { TelemetryHub } from "@/lib/telemetry/hub";

describe("TelemetryHub", () => {
  it("only delivers events for subscribed cranes", () => {
    const hub = new TelemetryHub(cranes, 60_000, 1);
    const events: RealtimeEvent[] = [];
    const unsubscribe = hub.subscribe(["c-201"], (e) => events.push(e));
    for (let i = 0; i < 30; i++) hub.step(Date.now() + i * 1000);
    unsubscribe();

    expect(events[0]?.type).toBe("snapshot");
    for (const e of events) {
      if (e.type === "telemetry") expect(e.points.every((p) => p.craneId === "c-201")).toBe(true);
      if (e.type === "alarm") expect(e.alarm.craneId).toBe("c-201");
      if (e.type === "snapshot") expect(Object.keys(e.history)).toEqual(["c-201"]);
    }
  });

  it("acknowledges alarms once and notifies subscribers", () => {
    const hub = new TelemetryHub(cranes, 60_000, 3);
    // Run until the simulator raises at least one alarm.
    let t = Date.now();
    while (hub.listAlarms(new Set(cranes.map((c) => c.id))).length === 0) hub.step((t += 1000));
    const alarm = hub.listAlarms(new Set(cranes.map((c) => c.id)))[0]!;

    const events: RealtimeEvent[] = [];
    hub.subscribe([alarm.craneId], (e) => events.push(e));
    const acked = hub.acknowledge(alarm.id, "Taras", 123);
    expect(acked).toMatchObject({ acknowledgedAt: 123, acknowledgedBy: "Taras" });
    expect(hub.acknowledge(alarm.id, "Someone else", 456)?.acknowledgedBy).toBe("Taras");
    expect(events.some((e) => e.type === "alarm" && e.alarm.id === alarm.id)).toBe(true);
  });
});
