export const SESSION_COOKIE = "cm_session";
export const SESSION_TTL_SEC = 60 * 60 * 8;

const DEV_SECRET = "dev-only-secret-do-not-use-in-production-0123456789";

export function sessionSecret(): string {
  const secret = process.env.SESSION_SECRET;
  if (secret && secret.length >= 32) return secret;
  if (process.env.NODE_ENV === "production" && process.env.DEMO_MODE !== "1") {
    throw new Error("SESSION_SECRET (>= 32 chars) is required in production");
  }
  return DEV_SECRET;
}
