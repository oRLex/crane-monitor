"use client";

import { useActionState } from "react";
import { requestReport, type FormState } from "@/lib/actions";

export function ReportForm({ cranes, defaultCrane }: { cranes: { id: string; name: string }[]; defaultCrane?: string }) {
  const [state, action, pending] = useActionState<FormState, FormData>(requestReport, {});
  return (
    <form action={action} className="space-y-3 text-sm">
      <label className="block">
        <span className="mb-1 block text-xs text-muted">Crane</span>
        <select name="craneId" defaultValue={defaultCrane ?? cranes[0]?.id} className="w-full rounded-lg border border-line bg-bg px-3 py-2">
          {cranes.map((c) => (
            <option key={c.id} value={c.id}>
              {c.name}
            </option>
          ))}
        </select>
      </label>
      <label className="block">
        <span className="mb-1 block text-xs text-muted">Period</span>
        <select name="hours" defaultValue="8" className="w-full rounded-lg border border-line bg-bg px-3 py-2">
          <option value="4">Last 4 hours</option>
          <option value="8">Last 8 hours (a shift)</option>
          <option value="12">Last 12 hours</option>
          <option value="24">Last 24 hours</option>
        </select>
      </label>
      {state.error && <p className="rounded-md bg-crit/10 px-3 py-2 text-crit">{state.error}</p>}
      <button type="submit" disabled={pending} className="w-full rounded-lg bg-crane py-2 font-medium text-bg disabled:opacity-60">
        {pending ? "Queueing…" : "Generate report"}
      </button>
    </form>
  );
}
