import { describe, expect, it } from "vitest";
import { cranes } from "@/lib/data/seed-data";
import { TelemetrySimulator } from "@/lib/telemetry/simulator";

describe("TelemetrySimulator", () => {
  it("is deterministic for a given seed", () => {
    const a = new TelemetrySimulator(cranes, { seed: 42 });
    const b = new TelemetrySimulator(cranes, { seed: 42 });
    for (let i = 0; i < 50; i++) expect(a.tick(i)).toEqual(b.tick(i));
  });

  it("produces physically bounded values over a long run", () => {
    const sim = new TelemetrySimulator(cranes, { seed: 7, dropoutChance: 0.01 });
    let sawLoad = false;
    let sawOffline = false;
    for (let i = 0; i < 2000; i++) {
      for (const p of sim.tick(i)) {
        const crane = cranes.find((c) => c.id === p.craneId)!;
        expect(p.radiusM).toBeGreaterThanOrEqual(0);
        expect(p.radiusM).toBeLessThanOrEqual(crane.jibLengthM);
        expect(p.loadT).toBeLessThanOrEqual(crane.maxLoadT * 1.1);
        expect(p.windMs).toBeGreaterThanOrEqual(0);
        expect(p.slewDeg).toBeGreaterThanOrEqual(0);
        expect(p.slewDeg).toBeLessThan(360.1);
        sawLoad ||= p.loadT > 1;
        sawOffline ||= !p.online;
      }
    }
    expect(sawLoad).toBe(true);
    expect(sawOffline).toBe(true);
  });
});
