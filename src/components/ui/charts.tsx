"use client";

import { useId } from "react";

interface Threshold {
  value: number;
  color: string;
  label?: string;
  /** Which edge the label sits on, to avoid overlaps between close thresholds. */
  align?: "left" | "right";
}

/** Minimal SVG sparkline — no chart library needed for small multiples. */
export function Sparkline({
  values,
  max,
  color = "var(--color-crane)",
  className = "h-8 w-full",
}: {
  values: number[];
  max: number;
  color?: string;
  className?: string;
}) {
  const id = useId();
  if (values.length < 2) return <div className={className} />;
  const w = 100;
  const h = 30;
  const pts = values.map((v, i) => [(i / (values.length - 1)) * w, h - (Math.min(v, max) / max) * h] as const);
  const d = pts.map(([x, y], i) => `${i ? "L" : "M"}${x.toFixed(2)},${y.toFixed(2)}`).join("");
  return (
    <svg viewBox={`0 0 ${w} ${h}`} preserveAspectRatio="none" className={className} aria-hidden>
      <defs>
        <linearGradient id={id} x1="0" x2="0" y1="0" y2="1">
          <stop offset="0" stopColor={color} stopOpacity="0.25" />
          <stop offset="1" stopColor={color} stopOpacity="0" />
        </linearGradient>
      </defs>
      <path d={`${d}L${w},${h}L0,${h}Z`} fill={`url(#${id})`} />
      <path d={d} fill="none" stroke={color} strokeWidth="1.5" vectorEffect="non-scaling-stroke" />
    </svg>
  );
}

/** Time-series chart with threshold bands, sized by its container. */
export function LineChart({
  label,
  unit,
  series,
  max,
  thresholds = [],
  color = "var(--color-crane)",
}: {
  label: string;
  unit: string;
  series: { t: number; v: number }[];
  max: number;
  thresholds?: Threshold[];
  color?: string;
}) {
  const w = 600;
  const h = 160;
  const last = series.at(-1);
  const t0 = series[0]?.t ?? 0;
  const t1 = last?.t ?? 1;
  const span = Math.max(1, t1 - t0);
  const x = (t: number) => ((t - t0) / span) * w;
  const y = (v: number) => h - (Math.min(v, max) / max) * h;
  const d = series.map((p, i) => `${i ? "L" : "M"}${x(p.t).toFixed(1)},${y(p.v).toFixed(1)}`).join("");

  return (
    <figure className="card p-4">
      <figcaption className="mb-3 flex items-baseline justify-between">
        <span className="text-sm text-muted">{label}</span>
        <span className="tabular font-mono text-lg">
          {last ? last.v.toFixed(1) : "—"}
          <span className="ml-1 text-xs text-muted">{unit}</span>
        </span>
      </figcaption>
      <div className="relative">
        <svg
          viewBox={`0 0 ${w} ${h}`}
          preserveAspectRatio="none"
          className="h-40 w-full overflow-visible"
          role="img"
          aria-label={`${label}, last ${Math.round(span / 1000)} seconds`}
        >
          {[0.25, 0.5, 0.75].map((f) => (
            <line key={f} x1="0" x2={w} y1={h * f} y2={h * f} stroke="var(--color-line)" vectorEffect="non-scaling-stroke" />
          ))}
          {thresholds.map((t) => (
            <line
              key={t.value}
              x1="0"
              x2={w}
              y1={y(t.value)}
              y2={y(t.value)}
              stroke={t.color}
              strokeDasharray="4 4"
              strokeOpacity="0.7"
              vectorEffect="non-scaling-stroke"
            />
          ))}
          <path d={d} fill="none" stroke={color} strokeWidth="2" vectorEffect="non-scaling-stroke" strokeLinejoin="round" />
        </svg>
        {thresholds.map((t) => (
          <span
            key={t.value}
            className={`tabular pointer-events-none absolute -translate-y-full pb-0.5 font-mono text-[10px] ${
              t.align === "left" ? "left-0" : "right-0"
            }`}
            style={{ top: `${(y(t.value) / h) * 100}%`, color: t.color }}
          >
            {t.label ?? t.value}
          </span>
        ))}
      </div>
      <div className="mt-1 flex justify-between font-mono text-[10px] text-faint">
        <span>−{Math.round(span / 1000)}s</span>
        <span>now</span>
      </div>
    </figure>
  );
}

/** Top-down view of the jib: slew angle + trolley radius. */
export function SlewGauge({
  slewDeg,
  radiusM,
  jibLengthM,
  className = "size-16",
}: {
  slewDeg: number;
  radiusM: number;
  jibLengthM: number;
  className?: string;
}) {
  const r = 44;
  const rad = ((slewDeg - 90) * Math.PI) / 180;
  const tx = 50 + Math.cos(rad) * r * (radiusM / jibLengthM);
  const ty = 50 + Math.sin(rad) * r * (radiusM / jibLengthM);
  const jx = 50 + Math.cos(rad) * r;
  const jy = 50 + Math.sin(rad) * r;
  const cx = 50 - Math.cos(rad) * 14;
  const cy = 50 - Math.sin(rad) * 14;
  return (
    <svg viewBox="0 0 100 100" className={className} role="img" aria-label={`Slew ${slewDeg}°, trolley at ${radiusM} m`}>
      <circle cx="50" cy="50" r={r} fill="none" stroke="var(--color-line)" />
      <circle cx="50" cy="50" r={r / 2} fill="none" stroke="var(--color-line)" strokeDasharray="2 3" />
      <line x1={cx} y1={cy} x2={jx} y2={jy} stroke="var(--color-crane)" strokeWidth="3" strokeLinecap="round" />
      <circle cx={tx} cy={ty} r="4" fill="var(--color-ink)" />
      <circle cx="50" cy="50" r="3.5" fill="var(--color-crane)" />
      <text x="50" y="9" textAnchor="middle" fontSize="8" fill="var(--color-faint)">N</text>
    </svg>
  );
}

export function MomentBar({ pct }: { pct: number }) {
  const color = pct >= 100 ? "var(--color-crit)" : pct >= 90 ? "var(--color-warn)" : "var(--color-ok)";
  return (
    <div className="relative h-1.5 w-full overflow-hidden rounded-full bg-surface-2" aria-hidden>
      <div className="h-full rounded-full transition-[width] duration-700" style={{ width: `${Math.min(pct, 100)}%`, background: color }} />
      <div className="absolute inset-y-0 left-[90%] w-px bg-warn/60" />
    </div>
  );
}
