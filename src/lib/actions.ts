"use server";

import { redirect } from "next/navigation";
import { revalidatePath } from "next/cache";
import { z } from "zod";
import { assertCan, canAccessTenant, ForbiddenError } from "@/lib/auth/rbac";
import { endSession, getPrincipal, startSession } from "@/lib/auth/session";
import { getRepository } from "@/lib/data";
import { getHub } from "@/lib/telemetry";

export interface FormState {
  error?: string;
}

const loginSchema = z.object({
  email: z.email(),
  // Only same-origin relative paths, to avoid an open redirect.
  next: z
    .string()
    .regex(/^\/(?!\/)[\w\-/]*$/)
    .catch("/"),
});

/**
 * Demo sign-in: pick a seeded user (no password). In production this is the
 * Entra ID / Azure AD B2C callback, which produces the same Principal.
 */
export async function signIn(_prev: FormState, formData: FormData): Promise<FormState> {
  const parsed = loginSchema.safeParse({ email: formData.get("email"), next: formData.get("next") ?? "/" });
  if (!parsed.success) return { error: "Choose a user to continue" };

  const user = await (await getRepository()).findUserByEmail(parsed.data.email);
  if (!user) return { error: "Unknown user" };

  await startSession({ sub: user.id, name: user.name, email: user.email, role: user.role, tenantId: user.tenantId });
  redirect(parsed.data.next);
}

export async function signOut(): Promise<void> {
  await endSession();
  redirect("/login");
}

export type AckResult = { ok: true; acknowledgedAt: number; acknowledgedBy: string } | { ok: false; error: string };

const ackSchema = z.object({ alarmId: z.string().min(1).max(100) });

export async function acknowledgeAlarm(alarmId: string): Promise<AckResult> {
  const parsed = ackSchema.safeParse({ alarmId });
  if (!parsed.success) return { ok: false, error: "Invalid alarm id" };

  try {
    const principal = await getPrincipal();
    // Re-check on the server: hiding the button in the UI is not authorization.
    assertCan(principal, "alarms:ack");

    const hub = await getHub();
    const alarm = hub.getAlarm(parsed.data.alarmId);
    const crane = alarm && (await (await getRepository()).getCrane(alarm.craneId));
    if (!alarm || !crane || !canAccessTenant(principal, crane.tenantId)) return { ok: false, error: "Alarm not found" };

    const updated = hub.acknowledge(alarm.id, principal.name);
    revalidatePath(`/cranes/${crane.id}`);
    return { ok: true, acknowledgedAt: updated!.acknowledgedAt!, acknowledgedBy: updated!.acknowledgedBy! };
  } catch (e) {
    if (e instanceof ForbiddenError) return { ok: false, error: "You are not allowed to acknowledge alarms" };
    throw e;
  }
}
