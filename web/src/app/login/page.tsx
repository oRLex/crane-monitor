import { api } from "@/lib/api/server";
import { LoginForm } from "./login-form";

export const metadata = { title: "Sign in" };

type Props = { searchParams: Promise<{ next?: string }> };

type DemoUser = { email: string; name: string; role: string; roleLabel: string; tenant: string | null };

const HINTS: Record<string, string> = {
  admin: "Everything, incl. uploads and AI usage",
  operator: "All tenants, video, acknowledge",
  customer_manager: "Own tenant, video, acknowledge, documents",
  customer_viewer: "Own tenant, read-only, no video",
};

export default async function LoginPage({ searchParams }: Props) {
  const { next } = await searchParams;
  const { users } = await api<{ users: DemoUser[] }>("/auth/users");
  const options = users.map((u) => ({ email: u.email, name: u.name, role: u.roleLabel, tenant: u.tenant ?? "All tenants", hint: HINTS[u.role] ?? "" }));

  return (
    <main className="grid min-h-dvh place-items-center px-4 py-10">
      <div className="w-full max-w-xl">
        <div className="mb-8 text-center">
          <svg viewBox="0 0 32 32" className="mx-auto mb-3 size-10" aria-hidden>
            <path d="M9 27V8M9 8h17M9 8l-4 4M22 8v7" stroke="var(--color-crane)" strokeWidth="2.2" fill="none" strokeLinecap="round" />
            <rect x="19.5" y="15" width="5" height="4" rx="1" fill="var(--color-crane)" />
          </svg>
          <h1 className="text-2xl font-semibold">CraneWatch</h1>
          <p className="mt-1 text-sm text-muted">Demo sign-in — pick a user to see how data, AI answers and tools change by role and tenant.</p>
        </div>
        <LoginForm users={options} next={next ?? "/"} />
      </div>
    </main>
  );
}
