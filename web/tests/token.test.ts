import { describe, expect, it } from "vitest";
import { signToken, verifyToken, type SessionPayload } from "@/lib/auth/token";

const secret = "test-secret-test-secret-test-secret-123";
const payload: SessionPayload = {
  sub: "u-op",
  name: "Тарас",
  email: "operator@demo.io",
  role: "operator",
  tenantId: null,
  exp: 2_000_000_000,
};

describe("session token", () => {
  it("round-trips a signed payload (including non-ASCII)", async () => {
    const token = await signToken(payload, secret);
    expect(await verifyToken(token, secret, 1_000)).toEqual(payload);
  });

  it("rejects tampered payloads", async () => {
    const token = await signToken(payload, secret);
    const [, sig] = token.split(".");
    const forged = Buffer.from(JSON.stringify({ ...payload, role: "admin" })).toString("base64url");
    expect(await verifyToken(`${forged}.${sig}`, secret, 1_000)).toBeNull();
  });

  it("rejects wrong secret, expired and malformed tokens", async () => {
    const token = await signToken(payload, secret);
    expect(await verifyToken(token, "another-secret-another-secret-12345", 1_000)).toBeNull();
    expect(await verifyToken(token, secret, 2_000_000_001)).toBeNull();
    expect(await verifyToken("garbage", secret)).toBeNull();
    expect(await verifyToken(undefined, secret)).toBeNull();
  });
});
