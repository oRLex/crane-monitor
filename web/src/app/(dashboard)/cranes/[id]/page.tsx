import type { Metadata } from "next";
import Link from "next/link";
import { CraneDetail } from "@/components/crane-detail";
import { api, getProfile } from "@/lib/api/server";
import { can, type Crane } from "@/lib/domain";

// Next.js 15: route params are async.
type Props = { params: Promise<{ id: string }> };

// The API 404s for other tenants' cranes, which api() turns into notFound().
const getCrane = async (id: string) => (await api<{ data: Crane }>(`/cranes/${encodeURIComponent(id)}`)).data;

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  return { title: (await getCrane((await params).id)).name };
}

export default async function CranePage({ params }: Props) {
  const { id } = await params;
  const [profile, crane] = await Promise.all([getProfile(), getCrane(id)]);

  return (
    <>
      <nav className="mb-4 text-sm text-muted">
        <Link href="/" className="hover:text-ink">
          ← Fleet
        </Link>
      </nav>
      <CraneDetail
        crane={{ ...crane, streamUrl: null }}
        site={crane.site}
        streamUrl={crane.streamUrl}
        canVideo={can(profile, "video:read")}
        canAck={can(profile, "alarms:ack")}
        canAsk={can(profile, "ai:use")}
        canReport={can(profile, "reports:write")}
        userName={profile.name}
      />
    </>
  );
}
