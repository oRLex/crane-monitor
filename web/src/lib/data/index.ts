import "server-only";
import type { Repository } from "./repository";
import { memoryRepository } from "./memory-repository";

let repoPromise: Promise<Repository> | undefined;

/** Picks the data source once per process: PostgreSQL via Prisma when DATABASE_URL is set. */
export function getRepository(): Promise<Repository> {
  repoPromise ??= process.env.DATABASE_URL
    ? import("./prisma-repository").then((m) => m.createPrismaRepository())
    : Promise.resolve(memoryRepository);
  return repoPromise;
}
