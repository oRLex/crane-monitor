import type { Crane, TelemetryPoint } from "@/lib/domain";
import { clamp, createRandom, round } from "./random";

type Phase = "idle" | "hoist" | "travel" | "lower";

interface CraneState {
  phase: Phase;
  phaseTicks: number;
  loadT: number;
  targetLoadT: number;
  radiusM: number;
  targetRadiusM: number;
  slewDeg: number;
  targetSlewDeg: number;
  hookHeightM: number;
  offlineTicks: number;
}

export interface SimulatorOptions {
  seed?: number;
  /** Per-tick probability that a crane drops its link for a while. */
  dropoutChance?: number;
}

/**
 * Generates plausible crane telemetry: lift cycles (hoist -> travel -> lower -> idle),
 * per-site gusty wind, occasional near-overload lifts and connectivity drop-outs.
 */
export class TelemetrySimulator {
  private readonly rand: () => number;
  private readonly states = new Map<string, CraneState>();
  private readonly wind = new Map<string, number>();
  private readonly dropoutChance: number;

  constructor(
    private readonly cranes: readonly Crane[],
    options: SimulatorOptions = {},
  ) {
    this.rand = createRandom(options.seed ?? Date.now());
    this.dropoutChance = options.dropoutChance ?? 0.002;
    for (const crane of cranes) {
      this.states.set(crane.id, {
        phase: "idle",
        phaseTicks: Math.floor(this.rand() * 5),
        loadT: 0,
        targetLoadT: 0,
        radiusM: crane.jibLengthM * 0.4,
        targetRadiusM: crane.jibLengthM * 0.4,
        slewDeg: this.rand() * 360,
        targetSlewDeg: this.rand() * 360,
        hookHeightM: 40,
        offlineTicks: 0,
      });
      if (!this.wind.has(crane.siteId)) this.wind.set(crane.siteId, 4 + this.rand() * 6);
    }
  }

  tick(ts: number = Date.now()): TelemetryPoint[] {
    for (const [siteId, w] of this.wind) {
      // Mean-reverting random walk with rare strong gusts.
      const gust = this.rand() < 0.01 ? 8 + this.rand() * 8 : 0;
      this.wind.set(siteId, clamp(w + (8 - w) * 0.05 + (this.rand() - 0.5) * 1.6 + gust, 0, 32));
    }
    return this.cranes.map((crane) => this.step(crane, ts));
  }

  private step(crane: Crane, ts: number): TelemetryPoint {
    const s = this.states.get(crane.id)!;
    const windMs = round(this.wind.get(crane.siteId) ?? 0);

    if (s.offlineTicks > 0 || this.rand() < this.dropoutChance) {
      s.offlineTicks = s.offlineTicks > 0 ? s.offlineTicks - 1 : 15 + Math.floor(this.rand() * 20);
      return { ...this.point(crane, s, ts, windMs), online: false };
    }

    s.phaseTicks--;
    if (s.phaseTicks <= 0) this.nextPhase(crane, s, windMs);

    const ease = 0.25;
    s.loadT += (s.targetLoadT - s.loadT) * ease;
    s.radiusM += (s.targetRadiusM - s.radiusM) * ease * 0.6;
    const dSlew = ((s.targetSlewDeg - s.slewDeg + 540) % 360) - 180;
    s.slewDeg = (s.slewDeg + dSlew * 0.2 + 360) % 360;
    const targetHook = s.phase === "hoist" || s.phase === "travel" ? 55 : 8;
    s.hookHeightM += (targetHook - s.hookHeightM) * 0.3;

    return this.point(crane, s, ts, windMs);
  }

  private nextPhase(crane: Crane, s: CraneState, windMs: number) {
    const order: Record<Phase, Phase> = { idle: "hoist", hoist: "travel", travel: "lower", lower: "idle" };
    // High wind: cranes are parked (weathervaning) instead of starting a new lift.
    s.phase = s.phase === "idle" && windMs > 18 ? "idle" : order[s.phase];
    s.phaseTicks = 4 + Math.floor(this.rand() * 8);

    if (s.phase === "hoist") {
      s.targetRadiusM = crane.jibLengthM * (0.2 + this.rand() * 0.75);
      const capacityAtRadius = Math.min(crane.maxLoadT, crane.ratedMomentTm / s.targetRadiusM);
      // ~6% of lifts are pushed close to or over the chart to exercise overload alarms.
      const utilisation = this.rand() < 0.06 ? 0.92 + this.rand() * 0.14 : 0.2 + this.rand() * 0.6;
      s.targetLoadT = capacityAtRadius * utilisation;
    } else if (s.phase === "travel") {
      s.targetSlewDeg = this.rand() * 360;
      s.targetRadiusM = clamp(s.radiusM + (this.rand() - 0.5) * 10, 3, crane.jibLengthM);
    } else if (s.phase === "idle") {
      s.targetLoadT = 0;
    }
  }

  private point(crane: Crane, s: CraneState, ts: number, windMs: number): TelemetryPoint {
    const loadT = round(Math.max(0, s.loadT), 2);
    const radiusM = round(s.radiusM);
    return {
      craneId: crane.id,
      ts,
      loadT,
      radiusM,
      loadMomentPct: round(((loadT * radiusM) / crane.ratedMomentTm) * 100),
      windMs,
      slewDeg: round(s.slewDeg),
      hookHeightM: round(s.hookHeightM),
      online: true,
    };
  }
}
