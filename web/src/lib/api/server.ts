import "server-only";
import { cookies } from "next/headers";
import { notFound, redirect } from "next/navigation";
import { cache } from "react";
import type { Profile } from "@/lib/domain";
import { TOKEN_COOKIE } from "./session";

export const API_URL = (process.env.API_URL ?? "http://localhost:8000/api").replace(/\/$/, "");

export class ApiError extends Error {
  constructor(
    public readonly status: number,
    message: string,
    public readonly body?: unknown,
  ) {
    super(message);
  }
}

export async function token(): Promise<string | undefined> {
  return (await cookies()).get(TOKEN_COOKIE)?.value;
}

/**
 * Server-side call to the Laravel API with the user's token. Authorization is the API's job;
 * 401 -> sign-in, 404 -> notFound() (other tenants' ids look like missing ones).
 */
export async function api<T>(path: string, init: RequestInit & { allow404?: boolean } = {}): Promise<T> {
  const bearer = await token();
  const res = await fetch(`${API_URL}${path}`, {
    ...init,
    headers: {
      Accept: "application/json",
      ...(init.body && !(init.body instanceof FormData) ? { "Content-Type": "application/json" } : {}),
      ...(bearer ? { Authorization: `Bearer ${bearer}` } : {}),
      ...init.headers,
    },
    cache: "no-store",
  });
  if (res.status === 401) redirect("/login");
  if (res.status === 404 && !init.allow404) notFound();
  const body = res.headers.get("content-type")?.includes("json") ? await res.json() : await res.text();
  if (!res.ok) {
    const message = typeof body === "object" && body && "message" in body ? String(body.message) : res.statusText;
    throw new ApiError(res.status, message, body);
  }
  return body as T;
}

/** The signed-in user, fetched once per request. */
export const getProfile = cache(async (): Promise<Profile> => {
  if (!(await token())) redirect("/login");
  return (await api<{ user: Profile }>("/me")).user;
});
