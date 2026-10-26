"use client";

import { useActionState, useEffect, useRef } from "react";
import { uploadDocument, type FormState } from "@/lib/actions";

export function UploadForm() {
  const [state, action, pending] = useActionState<FormState, FormData>(uploadDocument, {});
  const form = useRef<HTMLFormElement>(null);
  useEffect(() => {
    if (state.ok) form.current?.reset();
  }, [state]);

  return (
    <form ref={form} action={action} className="space-y-2 text-sm">
      <input name="title" placeholder="Title" required className="w-full rounded-lg border border-line bg-bg px-3 py-2 outline-none focus:border-crane" />
      <textarea
        name="content"
        rows={8}
        placeholder={"## Section\nText of the procedure…"}
        className="w-full rounded-lg border border-line bg-bg px-3 py-2 font-mono text-xs outline-none focus:border-crane"
      />
      <input name="file" type="file" accept=".md,.txt,text/markdown,text/plain" className="w-full text-xs text-muted" />
      {state.error && <p className="rounded-md bg-crit/10 px-3 py-2 text-crit">{state.error}</p>}
      {state.ok && <p className="rounded-md bg-ok/10 px-3 py-2 text-ok">Queued for indexing.</p>}
      <button type="submit" disabled={pending} className="w-full rounded-lg bg-crane py-2 font-medium text-bg disabled:opacity-60">
        {pending ? "Uploading…" : "Add document"}
      </button>
    </form>
  );
}
