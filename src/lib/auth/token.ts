import type { Principal } from "./rbac";

/**
 * Minimal signed-token implementation (HMAC-SHA256 over a base64url JSON payload).
 * Uses Web Crypto only, so it runs both in Node route handlers and in Edge middleware.
 * In production this is replaced by Entra ID / Azure AD B2C tokens; the Principal shape stays.
 */

export interface SessionPayload extends Principal {
  /** Expiry, unix seconds. */
  exp: number;
}

const encoder = new TextEncoder();

function base64url(bytes: Uint8Array): string {
  let bin = "";
  for (const b of bytes) bin += String.fromCharCode(b);
  return btoa(bin).replace(/\+/g, "-").replace(/\//g, "_").replace(/=+$/, "");
}

function fromBase64url(input: string): Uint8Array {
  const b64 = input.replace(/-/g, "+").replace(/_/g, "/") + "===".slice((input.length + 3) % 4);
  const bin = atob(b64);
  const out = new Uint8Array(bin.length);
  for (let i = 0; i < bin.length; i++) out[i] = bin.charCodeAt(i);
  return out;
}

async function hmacKey(secret: string): Promise<CryptoKey> {
  return crypto.subtle.importKey("raw", encoder.encode(secret), { name: "HMAC", hash: "SHA-256" }, false, [
    "sign",
    "verify",
  ]);
}

export async function signToken(payload: SessionPayload, secret: string): Promise<string> {
  const body = base64url(encoder.encode(JSON.stringify(payload)));
  const sig = await crypto.subtle.sign("HMAC", await hmacKey(secret), encoder.encode(body));
  return `${body}.${base64url(new Uint8Array(sig))}`;
}

export async function verifyToken(
  token: string | undefined | null,
  secret: string,
  nowSec: number = Math.floor(Date.now() / 1000),
): Promise<SessionPayload | null> {
  if (!token) return null;
  const [body, sig] = token.split(".");
  if (!body || !sig) return null;
  try {
    const ok = await crypto.subtle.verify(
      "HMAC",
      await hmacKey(secret),
      fromBase64url(sig) as BufferSource,
      encoder.encode(body),
    );
    if (!ok) return null;
    const payload = JSON.parse(new TextDecoder().decode(fromBase64url(body))) as SessionPayload;
    if (typeof payload.exp !== "number" || payload.exp < nowSec) return null;
    return payload;
  } catch {
    return null;
  }
}
