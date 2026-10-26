import { FleetView } from "@/components/fleet-view";
import { visibleCranes, visibleSites } from "@/lib/access";
import { can } from "@/lib/auth/rbac";
import { requirePrincipal } from "@/lib/auth/session";

export const metadata = { title: "Fleet" };

export default async function FleetPage() {
  const principal = await requirePrincipal();
  const [cranes, sites] = await Promise.all([visibleCranes(principal), visibleSites(principal)]);
  // Strip stream URLs before they cross into client components.
  const safeCranes = cranes.map((c) => ({ ...c, streamUrl: null }));

  return <FleetView cranes={safeCranes} sites={sites} canAck={can(principal, "alarms:ack")} userName={principal.name} />;
}
