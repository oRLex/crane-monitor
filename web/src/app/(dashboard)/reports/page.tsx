import { ReportForm } from "@/components/reports/report-form";
import { ReportList } from "@/components/reports/report-list";
import { api, getProfile } from "@/lib/api/server";
import { can, type Crane, type ShiftReport } from "@/lib/domain";

export const metadata = { title: "Shift reports" };

type Props = { searchParams: Promise<{ crane?: string }> };

export default async function ReportsPage({ searchParams }: Props) {
  const { crane } = await searchParams;
  const [profile, { data: cranes }, { reports }] = await Promise.all([
    getProfile(),
    api<{ data: Crane[] }>("/cranes"),
    api<{ reports: ShiftReport[] }>("/reports"),
  ]);

  return (
    <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_340px]">
      <div className="min-w-0 space-y-4">
        <header>
          <h1 className="text-xl font-semibold">Shift reports</h1>
          <p className="text-sm text-muted">
            Numbers are computed from telemetry aggregates and alarms; the model writes the summary and recommendations, which are validated against those
            facts before they're saved. Identical inputs reuse the earlier report instead of paying again.
          </p>
        </header>
        <ReportList initial={reports} />
      </div>
      {can(profile, "reports:write") && (
        <aside className="card h-fit p-4">
          <h2 className="mb-3 font-semibold">New report</h2>
          <ReportForm cranes={cranes.map((c) => ({ id: c.id, name: c.name }))} defaultCrane={crane} />
        </aside>
      )}
    </div>
  );
}
