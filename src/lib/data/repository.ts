import type { Crane, Site, Tenant, User } from "@/lib/domain";

/** Read-side data access. Implemented in-memory for the demo and by Prisma for PostgreSQL. */
export interface Repository {
  listTenants(): Promise<Tenant[]>;
  listSites(): Promise<Site[]>;
  listCranes(): Promise<Crane[]>;
  getCrane(id: string): Promise<Crane | null>;
  listUsers(): Promise<User[]>;
  findUserByEmail(email: string): Promise<User | null>;
}
