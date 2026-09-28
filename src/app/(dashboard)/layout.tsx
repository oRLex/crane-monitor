import Link from "next/link";
import { TelemetryProvider } from "@/components/telemetry-provider";
import { visibleCranes } from "@/lib/access";
import { signOut } from "@/lib/actions";
import { ROLE_LABELS, scopesOf } from "@/lib/auth/rbac";
import { requirePrincipal } from "@/lib/auth/session";
import { getRepository } from "@/lib/data";

export default async function DashboardLayout({ children }: { children: React.ReactNode }) {
  const principal = await requirePrincipal();
  const [cranes, tenants] = await Promise.all([
    visibleCranes(principal),
    getRepository().then((r) => r.listTenants()),
  ]);
  const tenant = tenants.find((t) => t.id === principal.tenantId);

  return (
    <TelemetryProvider craneIds={cranes.map((c) => c.id)}>
      <header className="sticky top-0 z-10 border-b border-line bg-bg/85 backdrop-blur">
        <div className="mx-auto flex h-14 max-w-[1600px] items-center gap-4 px-4 sm:px-6">
          <Link href="/" className="flex items-center gap-2 font-semibold">
            <svg viewBox="0 0 32 32" className="size-6" aria-hidden>
              <path
                d="M9 27V8M9 8h17M9 8l-4 4M22 8v7"
                stroke="var(--color-crane)"
                strokeWidth="2.4"
                fill="none"
                strokeLinecap="round"
              />
              <rect x="19.5" y="15" width="5" height="4" rx="1" fill="var(--color-crane)" />
            </svg>
            CraneWatch
          </Link>
          <span className="hidden text-sm text-faint sm:inline">{tenant ? tenant.name : "All tenants"}</span>

          <div className="ml-auto flex items-center gap-4">
            <details className="relative">
              <summary className="cursor-pointer list-none text-right text-sm">
                <span className="block leading-tight">{principal.name}</span>
                <span className="block text-xs leading-tight text-muted">{ROLE_LABELS[principal.role]}</span>
              </summary>
              <div className="card absolute right-0 mt-2 w-64 p-3 text-xs shadow-xl">
                <p className="mb-2 text-muted">Granted scopes</p>
                <ul className="flex flex-wrap gap-1">
                  {scopesOf(principal).map((s) => (
                    <li key={s} className="rounded bg-surface-2 px-1.5 py-0.5 font-mono">
                      {s}
                    </li>
                  ))}
                </ul>
              </div>
            </details>
            <form action={signOut}>
              <button type="submit" className="rounded-md border border-line px-2.5 py-1 text-sm text-muted hover:text-ink">
                Sign out
              </button>
            </form>
          </div>
        </div>
      </header>
      <main className="mx-auto max-w-[1600px] px-4 py-6 sm:px-6">{children}</main>
    </TelemetryProvider>
  );
}
