import { NextResponse } from "next/server";
import { can } from "@/lib/auth/rbac";
import { getPrincipal } from "@/lib/auth/session";
import { sessionSecret } from "@/lib/auth/config";
import { signToken } from "@/lib/auth/token";

export const dynamic = "force-dynamic";

/**
 * Short-lived bearer token for the SignalR hub (accessTokenFactory).
 * The hub validates it and places the connection into tenant groups based on its claims.
 */
export async function GET() {
  const principal = await getPrincipal();
  if (!principal || !can(principal, "telemetry:read")) {
    return NextResponse.json({ error: "unauthorized" }, { status: 401 });
  }
  const token = await signToken({ ...principal, exp: Math.floor(Date.now() / 1000) + 300 }, sessionSecret());
  return NextResponse.json({ token, expiresIn: 300 }, { headers: { "Cache-Control": "no-store" } });
}
