import { FleetView } from "@/components/fleet-view";
import { api, getProfile } from "@/lib/api/server";
import { can, type Crane, type Site } from "@/lib/domain";

export const metadata = { title: "Fleet" };

export default async function FleetPage() {
  const [profile, { data: cranes }] = await Promise.all([getProfile(), api<{ data: Crane[] }>("/cranes")]);
  const sites = [...new Map(cranes.filter((c) => c.site).map((c) => [c.siteId, c.site as Site])).values()];
  // The fleet view doesn't play video: keep stream URLs out of client props entirely.
  const safeCranes = cranes.map((c) => ({ ...c, streamUrl: null }));

  return (
    <FleetView
      cranes={safeCranes}
      sites={sites}
      canAck={can(profile, "alarms:ack")}
      canAsk={can(profile, "ai:use")}
      userName={profile.name}
    />
  );
}
