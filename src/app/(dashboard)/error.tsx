"use client";

export default function DashboardError({ error, reset }: { error: Error & { digest?: string }; reset: () => void }) {
  return (
    <div className="card mx-auto mt-16 max-w-md p-6 text-center">
      <h1 className="mb-2 font-semibold">Something went wrong</h1>
      <p className="mb-4 text-sm text-muted">{error.digest ? `Error reference: ${error.digest}` : error.message}</p>
      <button type="button" onClick={reset} className="rounded-md bg-crane px-3 py-1.5 text-sm font-medium text-bg">
        Try again
      </button>
    </div>
  );
}
