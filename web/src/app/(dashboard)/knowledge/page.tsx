import Link from "next/link";
import { KnowledgeSearchBox } from "@/components/knowledge/search-box";
import { UploadForm } from "@/components/knowledge/upload-form";
import { api, getProfile } from "@/lib/api/server";
import { can, type KnowledgeDocument } from "@/lib/domain";

export const metadata = { title: "Knowledge" };

export default async function KnowledgePage() {
  const [profile, { documents }] = await Promise.all([getProfile(), api<{ documents: KnowledgeDocument[] }>("/knowledge/documents")]);
  const own = documents.filter((d) => d.tenant_id !== null);
  const library = documents.filter((d) => d.tenant_id === null);

  return (
    <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_380px]">
      <div className="min-w-0 space-y-6">
        <header>
          <h1 className="text-xl font-semibold">Knowledge base</h1>
          <p className="text-sm text-muted">
            Manuals and procedures the assistant searches (hybrid: pgvector + full-text). Customer documents are only visible within their organisation.
          </p>
        </header>
        <KnowledgeSearchBox />
        <DocTable title={profile.tenantName ? `${profile.tenantName} documents` : "Customer documents"} docs={own} showTenant={can(profile, "tenants:all")} />
        <DocTable title="Platform library" docs={library} />
      </div>
      {can(profile, "docs:write") && (
        <aside className="card h-fit p-4">
          <h2 className="mb-1 font-semibold">Add a document</h2>
          <p className="mb-3 text-xs text-muted">
            Markdown or plain text. It's chunked by headings, embedded on the queue and searchable in about a second.
            {profile.tenantId ? ` Visible to ${profile.tenantName} only.` : " Platform roles add to the shared library."}
          </p>
          <UploadForm />
        </aside>
      )}
    </div>
  );
}

function DocTable({ title, docs, showTenant = false }: { title: string; docs: KnowledgeDocument[]; showTenant?: boolean }) {
  return (
    <section className="card">
      <h2 className="border-b border-line px-4 py-3 font-semibold">{title}</h2>
      {docs.length === 0 ? (
        <p className="px-4 py-6 text-center text-sm text-faint">No documents</p>
      ) : (
        <ul className="divide-y divide-line">
          {docs.map((d) => (
            <li key={d.id} className="flex items-center gap-3 px-4 py-3 text-sm">
              <Link href={`/knowledge/${d.id}`} className="min-w-0 flex-1 truncate hover:text-crane">
                {d.title}
              </Link>
              {showTenant && d.tenant_id && <span className="text-xs text-faint">{d.tenant_id}</span>}
              <span className="tabular text-xs text-faint">{d.chunk_count} chunks</span>
              <span className={`text-xs ${d.status === "indexed" ? "text-ok" : d.status === "failed" ? "text-crit" : "text-warn"}`} title={d.error ?? undefined}>
                {d.status}
              </span>
            </li>
          ))}
        </ul>
      )}
    </section>
  );
}
