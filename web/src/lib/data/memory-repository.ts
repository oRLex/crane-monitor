import type { Repository } from "./repository";
import { cranes, sites, tenants, users } from "./seed-data";

export const memoryRepository: Repository = {
  listTenants: async () => [...tenants],
  listSites: async () => [...sites],
  listCranes: async () => [...cranes],
  getCrane: async (id) => cranes.find((c) => c.id === id) ?? null,
  listUsers: async () => [...users],
  findUserByEmail: async (email) => users.find((u) => u.email === email.toLowerCase()) ?? null,
};
