import "server-only";
import { cookies } from "next/headers";
import { redirect } from "next/navigation";
import type { Principal } from "./rbac";
import { SESSION_COOKIE, SESSION_TTL_SEC, sessionSecret } from "./config";
import { signToken, verifyToken } from "./token";

export async function getPrincipal(): Promise<Principal | null> {
  const store = await cookies();
  const payload = await verifyToken(store.get(SESSION_COOKIE)?.value, sessionSecret());
  if (!payload) return null;
  const { sub, name, email, role, tenantId } = payload;
  return { sub, name, email, role, tenantId };
}

export async function requirePrincipal(): Promise<Principal> {
  const principal = await getPrincipal();
  if (!principal) redirect("/login");
  return principal;
}

export async function startSession(principal: Principal): Promise<void> {
  const token = await signToken(
    { ...principal, exp: Math.floor(Date.now() / 1000) + SESSION_TTL_SEC },
    sessionSecret(),
  );
  const store = await cookies();
  store.set(SESSION_COOKIE, token, {
    httpOnly: true,
    sameSite: "lax",
    secure: process.env.NODE_ENV === "production",
    path: "/",
    maxAge: SESSION_TTL_SEC,
  });
}

export async function endSession(): Promise<void> {
  (await cookies()).delete(SESSION_COOKIE);
}
