"use server";

import { cookies } from "next/headers";
import { revalidatePath } from "next/cache";
import { redirect } from "next/navigation";
import { z } from "zod";
import { api, ApiError } from "@/lib/api/server";
import { TOKEN_COOKIE, TOKEN_TTL_SEC } from "@/lib/api/session";
import type { Alarm, ShiftReport } from "@/lib/domain";

export interface FormState {
  error?: string;
  ok?: boolean;
}

const loginSchema = z.object({
  email: z.email(),
  // Only same-origin relative paths, to avoid an open redirect.
  next: z
    .string()
    .regex(/^\/(?!\/)[\w\-/]*$/)
    .catch("/"),
});

/** Demo sign-in: exchange a seeded user for a Sanctum token kept in an httpOnly cookie. */
export async function signIn(_prev: FormState, formData: FormData): Promise<FormState> {
  const parsed = loginSchema.safeParse({ email: formData.get("email"), next: formData.get("next") ?? "/" });
  if (!parsed.success) return { error: "Choose a user to continue" };

  try {
    const { token } = await api<{ token: string }>("/auth/login", {
      method: "POST",
      body: JSON.stringify({ email: parsed.data.email, name: "web" }),
    });
    (await cookies()).set(TOKEN_COOKIE, token, {
      httpOnly: true,
      sameSite: "lax",
      secure: process.env.NODE_ENV === "production",
      path: "/",
      maxAge: TOKEN_TTL_SEC,
    });
  } catch (e) {
    return { error: e instanceof ApiError ? e.message : "Sign-in failed" };
  }
  redirect(parsed.data.next);
}

export async function signOut(): Promise<void> {
  await api("/auth/logout", { method: "POST" }).catch(() => undefined);
  (await cookies()).delete(TOKEN_COOKIE);
  redirect("/login");
}

export type AckResult = { ok: true; alarm: Alarm } | { ok: false; error: string };

export async function acknowledgeAlarm(alarmId: string): Promise<AckResult> {
  if (!z.string().min(1).max(40).safeParse(alarmId).success) return { ok: false, error: "Invalid alarm id" };
  try {
    // The API re-checks scope and tenant: hiding the button is not authorization.
    const { alarm } = await api<{ alarm: Alarm }>(`/alarms/${encodeURIComponent(alarmId)}/ack`, { method: "POST", allow404: true });
    return { ok: true, alarm };
  } catch (e) {
    if (e instanceof ApiError && e.status === 403) return { ok: false, error: "You are not allowed to acknowledge alarms" };
    if (e instanceof ApiError && e.status === 404) return { ok: false, error: "Alarm not found" };
    throw e;
  }
}

const reportSchema = z.object({ craneId: z.string().min(1).max(40), hours: z.coerce.number().int().min(1).max(24) });

export async function requestReport(_prev: FormState, formData: FormData): Promise<FormState> {
  const parsed = reportSchema.safeParse({ craneId: formData.get("craneId"), hours: formData.get("hours") });
  if (!parsed.success) return { error: "Choose a crane and a period" };
  try {
    await api<{ report: ShiftReport }>("/reports", {
      method: "POST",
      body: JSON.stringify({ crane_id: parsed.data.craneId, hours: parsed.data.hours }),
    });
  } catch (e) {
    return { error: e instanceof ApiError ? e.message : "Could not queue the report" };
  }
  revalidatePath("/reports");
  return { ok: true };
}

const docSchema = z.object({ title: z.string().trim().min(3).max(160), content: z.string().trim().min(20).max(200_000) });

export async function uploadDocument(_prev: FormState, formData: FormData): Promise<FormState> {
  const file = formData.get("file");
  const content = file instanceof File && file.size > 0 ? await file.text() : formData.get("content");
  const parsed = docSchema.safeParse({ title: formData.get("title"), content });
  if (!parsed.success) return { error: "Add a title and at least a paragraph of text (Markdown or plain text)" };
  try {
    await api("/knowledge/documents", { method: "POST", body: JSON.stringify(parsed.data) });
  } catch (e) {
    return { error: e instanceof ApiError ? e.message : "Upload failed" };
  }
  revalidatePath("/knowledge");
  return { ok: true };
}

export async function deleteDocument(id: number): Promise<void> {
  await api(`/knowledge/documents/${id}`, { method: "DELETE" });
  revalidatePath("/knowledge");
}
