---
paths:
  - "web/src/**/*.{ts,tsx}"
---

# Web (Next.js 15 / React 19)

- Data comes from the Laravel API. Server components and server actions call `api()` from `src/lib/api/server.ts`; client components call only `/api/backend/*`, and any new path must be added to the allow-list regex in `src/app/api/backend/[...path]/route.ts`.
- The API token lives only in the httpOnly `cw_token` cookie. Never expose it to client code, `NEXT_PUBLIC_*` variables or URLs.
- UI hides what a user can't do (`can(profile, scope)`), but that's UX only — the API enforces it. Never pass data to a client component that the user isn't allowed to see (e.g. `streamUrl` without `video:read`).
- Model output is rendered with `components/assistant/markdown.tsx` (React elements). No `dangerouslySetInnerHTML`.
- Wire types live in `src/lib/domain.ts` and mirror the API exactly; update both sides together.
- Styling: Tailwind v4 tokens from `globals.css` (`bg-surface`, `text-muted`, `text-crane`, `card`, `tabular`). No new colour literals.
- Next 15 specifics: `params`, `searchParams` and `cookies()` are async; use `useActionState`/`useOptimistic` for mutations like the existing forms.
