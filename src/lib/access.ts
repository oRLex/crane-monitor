import "server-only";
import { notFound } from "next/navigation";
import type { Crane, Site } from "@/lib/domain";
import { getRepository } from "@/lib/data";
import { type Principal, scopeToTenant } from "@/lib/auth/rbac";

/** All tenant-scoped reads go through here, so isolation is enforced in one place. */
export async function visibleCranes(principal: Principal): Promise<Crane[]> {
  const repo = await getRepository();
  return scopeToTenant(principal, await repo.listCranes());
}

export async function visibleSites(principal: Principal): Promise<Site[]> {
  const repo = await getRepository();
  return scopeToTenant(principal, await repo.listSites());
}

/** 404 (not 403) for cranes of other tenants, so ids don't leak across customers. */
export async function craneOr404(principal: Principal, id: string): Promise<Crane> {
  const crane = (await visibleCranes(principal)).find((c) => c.id === id);
  if (!crane) notFound();
  return crane;
}
