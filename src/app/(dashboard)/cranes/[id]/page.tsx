import type { Metadata } from "next";
import Link from "next/link";
import { CraneDetail } from "@/components/crane-detail";
import { craneOr404, visibleSites } from "@/lib/access";
import { can } from "@/lib/auth/rbac";
import { requirePrincipal } from "@/lib/auth/session";

// Next.js 15: route params are async.
type Props = { params: Promise<{ id: string }> };

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const principal = await requirePrincipal();
  const crane = await craneOr404(principal, (await params).id);
  return { title: crane.name };
}

export default async function CranePage({ params }: Props) {
  const { id } = await params;
  const principal = await requirePrincipal();
  const crane = await craneOr404(principal, id);
  const site = (await visibleSites(principal)).find((s) => s.id === crane.siteId);
  const canVideo = can(principal, "video:read");

  return (
    <>
      <nav className="mb-4 text-sm text-muted">
        <Link href="/" className="hover:text-ink">
          ← Fleet
        </Link>
      </nav>
      <CraneDetail
        crane={{ ...crane, streamUrl: null }}
        site={site}
        streamUrl={canVideo ? crane.streamUrl : null}
        canVideo={canVideo}
        canAck={can(principal, "alarms:ack")}
        userName={principal.name}
      />
    </>
  );
}
