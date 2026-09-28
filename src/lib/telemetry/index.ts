import "server-only";
import { getRepository } from "@/lib/data";
import { TelemetryHub } from "./hub";

const globalForHub = globalThis as unknown as { __telemetryHub?: Promise<TelemetryHub> };

/** One hub per server process (survives dev hot reloads). */
export function getHub(): Promise<TelemetryHub> {
  globalForHub.__telemetryHub ??= getRepository().then(async (repo) => new TelemetryHub(await repo.listCranes()));
  return globalForHub.__telemetryHub;
}
