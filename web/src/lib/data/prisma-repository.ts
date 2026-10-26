import type { Crane, Role, User } from "@/lib/domain";
import type { Repository } from "./repository";

/**
 * Prisma + PostgreSQL implementation. Loaded lazily (see ./index.ts) so the demo
 * runs without a database or a generated client.
 */
export async function createPrismaRepository(): Promise<Repository> {
  const { PrismaClient } = await import("@prisma/client");
  const globalForPrisma = globalThis as unknown as { __prisma?: InstanceType<typeof PrismaClient> };
  const prisma = (globalForPrisma.__prisma ??= new PrismaClient());

  const toUser = (u: { id: string; email: string; name: string; role: string; tenantId: string | null }): User => ({
    ...u,
    role: u.role as Role,
  });

  return {
    listTenants: () => prisma.tenant.findMany({ orderBy: { name: "asc" } }),
    listSites: () => prisma.site.findMany({ orderBy: { name: "asc" } }),
    listCranes: () => prisma.crane.findMany({ orderBy: { name: "asc" } }) as Promise<Crane[]>,
    getCrane: (id) => prisma.crane.findUnique({ where: { id } }) as Promise<Crane | null>,
    listUsers: async () => (await prisma.user.findMany()).map(toUser),
    findUserByEmail: async (email) => {
      const u = await prisma.user.findUnique({ where: { email: email.toLowerCase() } });
      return u ? toUser(u) : null;
    },
  };
}
