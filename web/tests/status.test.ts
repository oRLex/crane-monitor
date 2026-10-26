import { describe, expect, it } from "vitest";
import type { Alarm, TelemetryPoint } from "@/lib/domain";
import { deriveStatus } from "@/lib/status";

const point = (over: Partial<TelemetryPoint> = {}): TelemetryPoint => ({
  craneId: "c-1", ts: 1, loadT: 2, radiusM: 20, loadMomentPct: 40, windMs: 5, slewDeg: 0, hookHeightM: 30, online: true, ...over,
});
const open: Alarm = {
  id: "x", craneId: "c-1", kind: "wind", severity: "warning", message: "", raisedAt: 0, acknowledgedAt: null, acknowledgedBy: null, clearedAt: null,
};

describe("deriveStatus (mirrors AlarmEngine::status)", () => {
  it("prioritises offline > alarm > operating > idle", () => {
    expect(deriveStatus(undefined, [])).toBe("offline");
    expect(deriveStatus(point({ online: false }), [open])).toBe("offline");
    expect(deriveStatus(point(), [open])).toBe("alarm");
    expect(deriveStatus(point(), [{ ...open, clearedAt: 5 }])).toBe("operating");
    expect(deriveStatus(point({ loadT: 0 }), [])).toBe("idle");
  });
});
