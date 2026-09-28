import { NextResponse } from "next/server";
import { can } from "@/lib/auth/rbac";
import { getPrincipal } from "@/lib/auth/session";
import { visibleCranes } from "@/lib/access";
import { getHub } from "@/lib/telemetry";
import { deriveStatus } from "@/lib/telemetry/alarms";

export const dynamic = "force-dynamic";

/** REST read model for integrations: cranes the caller may see, with current status. */
export async function GET() {
  const principal = await getPrincipal();
  if (!principal) return NextResponse.json({ error: "unauthorized" }, { status: 401 });
  if (!can(principal, "cranes:read")) return NextResponse.json({ error: "forbidden" }, { status: 403 });

  const cranes = await visibleCranes(principal);
  const hub = await getHub();
  const snap = hub.snapshot(new Set(cranes.map((c) => c.id)));
  const canVideo = can(principal, "video:read");

  return NextResponse.json({
    data: cranes.map((c) => {
      const last = snap.points.find((p) => p.craneId === c.id);
      return {
        ...c,
        // Field-level RBAC: stream URLs are only exposed to principals with video:read.
        streamUrl: canVideo ? c.streamUrl : null,
        status: deriveStatus(last, snap.alarms),
        lastTelemetry: last ?? null,
      };
    }),
  });
}
