import { describe, expect, it } from "vitest";
import type { Alarm, TelemetryPoint } from "@/lib/domain";
import { deriveStatus, evaluate, reconcile } from "@/lib/telemetry/alarms";

const point = (over: Partial<TelemetryPoint> = {}): TelemetryPoint => ({
  craneId: "c-1",
  ts: 1000,
  loadT: 2,
  radiusM: 20,
  loadMomentPct: 40,
  windMs: 5,
  slewDeg: 0,
  hookHeightM: 30,
  online: true,
  ...over,
});

let n = 0;
const nextId = () => `a${++n}`;

describe("evaluate", () => {
  it("is quiet under normal conditions", () => {
    expect(evaluate(point())).toEqual([]);
  });

  it("grades wind and overload", () => {
    expect(evaluate(point({ windMs: 15 }))).toMatchObject([{ kind: "wind", severity: "warning" }]);
    expect(evaluate(point({ windMs: 21 }))).toMatchObject([{ kind: "wind", severity: "critical" }]);
    expect(evaluate(point({ loadMomentPct: 95 }))).toMatchObject([{ kind: "overload", severity: "warning" }]);
    expect(evaluate(point({ loadMomentPct: 104 }))).toMatchObject([{ kind: "overload", severity: "critical" }]);
  });

  it("reports only offline when the link is down", () => {
    expect(evaluate(point({ online: false, windMs: 30 }))).toEqual([
      { kind: "offline", severity: "critical", message: "Telemetry link lost" },
    ]);
  });
});

describe("reconcile", () => {
  it("raises once, escalates, then clears", () => {
    let active: Alarm[] = [];
    const apply = (changed: Alarm[]) => {
      for (const a of changed) active = [a, ...active.filter((x) => x.id !== a.id)];
      return changed;
    };

    const raised = apply(reconcile(active, evaluate(point({ windMs: 15 })), "c-1", 1, nextId));
    expect(raised).toHaveLength(1);
    expect(apply(reconcile(active, evaluate(point({ windMs: 16 })), "c-1", 2, nextId))).toHaveLength(0);

    const escalated = apply(reconcile(active, evaluate(point({ windMs: 22 })), "c-1", 3, nextId));
    expect(escalated.map((a) => [a.severity, a.clearedAt])).toEqual([
      ["warning", 3],
      ["critical", null],
    ]);

    const cleared = apply(reconcile(active, evaluate(point()), "c-1", 4, nextId));
    expect(cleared).toHaveLength(1);
    expect(active.every((a) => a.clearedAt !== null)).toBe(true);
  });

  it("does not touch other cranes' alarms", () => {
    const other: Alarm = {
      id: "x",
      craneId: "c-2",
      kind: "wind",
      severity: "warning",
      message: "",
      raisedAt: 0,
      acknowledgedAt: null,
      acknowledgedBy: null,
      clearedAt: null,
    };
    expect(reconcile([other], [], "c-1", 1, nextId)).toEqual([]);
  });
});

describe("deriveStatus", () => {
  it("prioritises offline > alarm > operating > idle", () => {
    const open: Alarm = {
      id: "x",
      craneId: "c-1",
      kind: "wind",
      severity: "warning",
      message: "",
      raisedAt: 0,
      acknowledgedAt: null,
      acknowledgedBy: null,
      clearedAt: null,
    };
    expect(deriveStatus(undefined, [])).toBe("offline");
    expect(deriveStatus(point({ online: false }), [open])).toBe("offline");
    expect(deriveStatus(point(), [open])).toBe("alarm");
    expect(deriveStatus(point(), [{ ...open, clearedAt: 5 }])).toBe("operating");
    expect(deriveStatus(point({ loadT: 0 }), [])).toBe("idle");
  });
});
