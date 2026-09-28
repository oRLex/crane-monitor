"use client";

import { useMemo } from "react";
import type { Crane, Site } from "@/lib/domain";
import { deriveStatus, THRESHOLDS } from "@/lib/telemetry/alarms";
import { AlarmList } from "./alarm-list";
import { HlsPlayer } from "./hls-player";
import { useTelemetry } from "./telemetry-provider";
import { LineChart, SlewGauge } from "./ui/charts";
import { ConnectionDot, levelColor, StatusPill } from "./ui/status";

const EMPTY: never[] = [];

export function CraneDetail({
  crane,
  site,
  streamUrl,
  canVideo,
  canAck,
  userName,
}: {
  crane: Crane;
  site: Site | undefined;
  /** null when the principal lacks video:read — the URL is never sent to the browser. */
  streamUrl: string | null;
  canVideo: boolean;
  canAck: boolean;
  userName: string;
}) {
  const point = useTelemetry((s) => s.latest[crane.id]);
  const history = useTelemetry((s) => s.history[crane.id] ?? EMPTY);
  const allAlarms = useTelemetry((s) => s.alarms);
  const connection = useTelemetry((s) => s.connection);

  const alarms = useMemo(() => allAlarms.filter((a) => a.craneId === crane.id), [allAlarms, crane.id]);
  const status = deriveStatus(point, alarms);
  const moment = useMemo(() => history.map((p) => ({ t: p.ts, v: p.online ? p.loadMomentPct : 0 })), [history]);
  const wind = useMemo(() => history.map((p) => ({ t: p.ts, v: p.windMs })), [history]);
  const load = useMemo(() => history.map((p) => ({ t: p.ts, v: p.online ? p.loadT : 0 })), [history]);

  const capacityAtRadius = point ? Math.min(crane.maxLoadT, crane.ratedMomentTm / Math.max(point.radiusM, 1)) : null;

  return (
    <div className="space-y-6">
      <header className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <div className="mb-1 flex items-center gap-3">
            <h1 className="text-2xl font-semibold">{crane.name}</h1>
            <StatusPill status={status} />
          </div>
          <p className="text-sm text-muted">
            {crane.model} · S/N {crane.serialNumber} · {site?.name}, {site?.city}
          </p>
        </div>
        <ConnectionDot state={connection} />
      </header>

      <dl className="grid grid-cols-2 gap-3 md:grid-cols-3 lg:grid-cols-6">
        <Tile label="Load" value={point?.loadT.toFixed(2)} unit="t" />
        <Tile label="Capacity @ radius" value={capacityAtRadius?.toFixed(2)} unit="t" />
        <Tile label="Radius" value={point?.radiusM.toFixed(1)} unit={`/ ${crane.jibLengthM} m`} />
        <Tile
          label="Load moment"
          value={point?.loadMomentPct.toFixed(0)}
          unit="%"
          color={point && levelColor(point.loadMomentPct, THRESHOLDS.loadMoment.warning, THRESHOLDS.loadMoment.critical)}
        />
        <Tile
          label="Wind"
          value={point?.windMs.toFixed(1)}
          unit="m/s"
          color={point && levelColor(point.windMs, THRESHOLDS.wind.warning, THRESHOLDS.wind.critical)}
        />
        <Tile label="Hook height" value={point?.hookHeightM.toFixed(0)} unit="m" />
      </dl>

      <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
        <section aria-label="Video" className="card p-4">
          <h2 className="mb-3 text-sm text-muted">Hook camera</h2>
          {!canVideo ? (
            <Placeholder text="Your role does not include video access (video:read)." />
          ) : streamUrl ? (
            <HlsPlayer src={streamUrl} title={`${crane.name} hook cam`} />
          ) : (
            <Placeholder text="No camera installed on this crane." />
          )}
        </section>

        <section aria-labelledby="position-heading" className="card flex flex-col p-4">
          <h2 id="position-heading" className="mb-3 text-sm text-muted">
            Jib position (top view)
          </h2>
          <div className="flex flex-1 flex-wrap items-center justify-center gap-10">
          <SlewGauge
            slewDeg={point?.slewDeg ?? 0}
            radiusM={point?.radiusM ?? 0}
            jibLengthM={crane.jibLengthM}
            className={`size-56 shrink-0 ${point?.online ? "" : "opacity-30"}`}
          />
          <dl className="tabular space-y-3 font-mono text-sm">
            <div>
              <dt className="font-sans text-xs text-faint">Slew</dt>
              <dd className="text-xl">{point ? `${point.slewDeg.toFixed(0)}°` : "—"}</dd>
            </div>
            <div>
              <dt className="font-sans text-xs text-faint">Rated moment</dt>
              <dd>{crane.ratedMomentTm} t·m</dd>
            </div>
            <div>
              <dt className="font-sans text-xs text-faint">Max load</dt>
              <dd>{crane.maxLoadT} t</dd>
            </div>
            <div>
              <dt className="font-sans text-xs text-faint">Last sample</dt>
              <dd>{point ? new Date(point.ts).toLocaleTimeString() : "—"}</dd>
            </div>
          </dl>
          </div>
        </section>
      </div>

      <div className="grid gap-4 lg:grid-cols-3">
        <LineChart
          label="Load moment"
          unit="%"
          series={moment}
          max={120}
          thresholds={[
            { value: THRESHOLDS.loadMoment.warning, color: "var(--color-warn)", label: "90%", align: "left" },
            { value: THRESHOLDS.loadMoment.critical, color: "var(--color-crit)", label: "100%" },
          ]}
        />
        <LineChart label="Load" unit="t" series={load} max={crane.maxLoadT * 1.1} color="var(--color-ink)" />
        <LineChart
          label="Wind speed"
          unit="m/s"
          series={wind}
          max={30}
          color="#60a5fa"
          thresholds={[
            { value: THRESHOLDS.wind.warning, color: "var(--color-warn)", label: "14" },
            { value: THRESHOLDS.wind.critical, color: "var(--color-crit)", label: "20" },
          ]}
        />
      </div>

      <section aria-labelledby="crane-alarms" className="card">
        <h2 id="crane-alarms" className="border-b border-line px-4 py-3 font-semibold">
          Alarm log
        </h2>
        <AlarmList alarms={alarms.slice(0, 50)} canAck={canAck} userName={userName} emptyText="No alarms in this session" />
      </section>
    </div>
  );
}

function Tile({ label, value, unit, color }: { label: string; value?: string; unit: string; color?: string }) {
  return (
    <div className="card px-4 py-3">
      <dt className="text-xs text-muted">{label}</dt>
      <dd className="tabular mt-1 font-mono text-xl" style={color ? { color } : undefined}>
        {value ?? "—"}
        <span className="ml-1 text-xs text-faint">{unit}</span>
      </dd>
    </div>
  );
}

function Placeholder({ text }: { text: string }) {
  return (
    <div className="grid aspect-video place-items-center rounded-lg border border-dashed border-line bg-surface-2 px-6 text-center text-sm text-faint">
      {text}
    </div>
  );
}
