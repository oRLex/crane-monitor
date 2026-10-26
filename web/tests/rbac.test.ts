import { describe, expect, it } from "vitest";
import { assertCan, can, canAccessTenant, ForbiddenError, scopeToTenant, type Principal } from "@/lib/auth/rbac";
import { cranes } from "@/lib/data/seed-data";

const p = (role: Principal["role"], tenantId: string | null = null): Principal => ({
  sub: "u",
  name: "U",
  email: "u@x",
  role,
  tenantId,
});

describe("rbac", () => {
  it("grants scopes by role", () => {
    expect(can(p("operator"), "alarms:ack")).toBe(true);
    expect(can(p("customer_manager", "t-budinvest"), "video:read")).toBe(true);
    expect(can(p("customer_viewer", "t-skyline"), "video:read")).toBe(false);
    expect(can(p("customer_viewer", "t-skyline"), "alarms:ack")).toBe(false);
    expect(can(null, "cranes:read")).toBe(false);
  });

  it("isolates tenants for customer roles", () => {
    const viewer = p("customer_viewer", "t-skyline");
    const visible = scopeToTenant(viewer, cranes);
    expect(visible.length).toBeGreaterThan(0);
    expect(visible.every((c) => c.tenantId === "t-skyline")).toBe(true);
    expect(canAccessTenant(viewer, "t-budinvest")).toBe(false);
  });

  it("lets platform roles see all tenants", () => {
    expect(scopeToTenant(p("operator"), cranes)).toHaveLength(cranes.length);
  });

  it("denies customer roles without a tenant", () => {
    expect(scopeToTenant(p("customer_manager", null), cranes)).toHaveLength(0);
  });

  it("assertCan throws ForbiddenError", () => {
    expect(() => assertCan(p("customer_viewer", "t-skyline"), "alarms:ack")).toThrow(ForbiddenError);
    expect(() => assertCan(null, "cranes:read")).toThrow(ForbiddenError);
  });
});
