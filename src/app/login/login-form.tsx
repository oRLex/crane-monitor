"use client";

import { useActionState } from "react";
import { signIn, type FormState } from "@/lib/actions";

interface UserOption {
  email: string;
  name: string;
  role: string;
  tenant: string;
  scopes: string[];
}

export function LoginForm({ users, next }: { users: UserOption[]; next: string }) {
  // React 19: action state + pending flag without extra client state.
  const [state, action, pending] = useActionState<FormState, FormData>(signIn, {});

  return (
    <form action={action} className="space-y-3">
      <input type="hidden" name="next" value={next} />
      <fieldset className="space-y-2">
        <legend className="sr-only">User</legend>
        {users.map((u, i) => (
          <label
            key={u.email}
            className="card flex cursor-pointer items-start gap-3 p-4 transition hover:border-faint has-checked:border-crane!"
          >
            <input type="radio" name="email" value={u.email} defaultChecked={i === 0} className="mt-1 accent-crane" />
            <span className="min-w-0 flex-1">
              <span className="flex flex-wrap items-baseline justify-between gap-x-3">
                <span className="font-medium">{u.name}</span>
                <span className="text-xs text-muted">{u.tenant}</span>
              </span>
              <span className="block text-sm text-crane">{u.role}</span>
              <span className="mt-2 flex flex-wrap gap-1">
                {u.scopes.map((s) => (
                  <span key={s} className="rounded bg-surface-2 px-1.5 py-0.5 font-mono text-[11px] text-muted">
                    {s}
                  </span>
                ))}
              </span>
            </span>
          </label>
        ))}
      </fieldset>
      {state.error && (
        <p role="alert" className="rounded-md bg-crit/10 px-3 py-2 text-sm text-crit">
          {state.error}
        </p>
      )}
      <button
        type="submit"
        disabled={pending}
        className="w-full rounded-lg bg-crane py-2.5 font-medium text-bg transition hover:brightness-110 disabled:opacity-60"
      >
        {pending ? "Signing in…" : "Continue"}
      </button>
    </form>
  );
}
