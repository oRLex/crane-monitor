import Link from "next/link";

export default function NotFound() {
  return (
    <main className="grid min-h-dvh place-items-center px-4">
      <div className="text-center">
        <p className="font-mono text-5xl text-crane">404</p>
        <p className="mt-2 text-muted">This crane doesn&apos;t exist or isn&apos;t shared with your organisation.</p>
        <Link href="/" className="mt-6 inline-block text-sm hover:text-crane">
          ← Back to fleet
        </Link>
      </div>
    </main>
  );
}
