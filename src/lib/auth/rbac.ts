import type { Role, Scope } from "@/lib/domain";

/**
 * Role -> scope mapping. Scopes are what the code checks; roles are just bundles.
 * Tenant isolation is a separate axis: every role except those holding
 * `tenants:all` is restricted to its own tenant's cranes.
 */
export const ROLE_SCOPES: Record<Role, readonly Scope[]> = {
  admin: ["tenants:all", "cranes:read", "telemetry:read", "video:read", "alarms:read", "alarms:ack"],
  operator: ["tenants:all", "cranes:read", "telemetry:read", "video:read", "alarms:read", "alarms:ack"],
  customer_manager: ["cranes:read", "telemetry:read", "video:read", "alarms:read", "alarms:ack"],
  customer_viewer: ["cranes:read", "telemetry:read", "alarms:read"],
};

export const ROLE_LABELS: Record<Role, string> = {
  admin: "Platform admin",
  operator: "Control-room operator",
  customer_manager: "Customer manager",
  customer_viewer: "Customer viewer",
};

export interface Principal {
  sub: string;
  name: string;
  email: string;
  role: Role;
  tenantId: string | null;
}

export function scopesOf(principal: Pick<Principal, "role">): readonly Scope[] {
  return ROLE_SCOPES[principal.role] ?? [];
}

export function can(principal: Pick<Principal, "role"> | null | undefined, scope: Scope): boolean {
  if (!principal) return false;
  return scopesOf(principal).includes(scope);
}

/** Tenant isolation check for a single resource. */
export function canAccessTenant(principal: Pick<Principal, "role" | "tenantId">, tenantId: string): boolean {
  if (can(principal, "tenants:all")) return true;
  return principal.tenantId !== null && principal.tenantId === tenantId;
}

/** Filters any tenant-owned collection down to what the principal may see. */
export function scopeToTenant<T extends { tenantId: string }>(
  principal: Pick<Principal, "role" | "tenantId">,
  items: readonly T[],
): T[] {
  return items.filter((item) => canAccessTenant(principal, item.tenantId));
}

export class ForbiddenError extends Error {
  constructor(message = "Forbidden") {
    super(message);
    this.name = "ForbiddenError";
  }
}

export function assertCan(principal: Principal | null, scope: Scope): asserts principal is Principal {
  if (!can(principal, scope)) throw new ForbiddenError(`Missing scope: ${scope}`);
}
