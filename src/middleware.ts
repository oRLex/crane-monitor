import { NextResponse, type NextRequest } from "next/server";
import { SESSION_COOKIE, sessionSecret } from "@/lib/auth/config";
import { verifyToken } from "@/lib/auth/token";

/**
 * Edge gate: unauthenticated users are redirected to /login (pages) or get 401 (API).
 * Fine-grained scope and tenant checks happen next to the data in route handlers,
 * server components and server actions.
 */
export async function middleware(req: NextRequest) {
  const session = await verifyToken(req.cookies.get(SESSION_COOKIE)?.value, sessionSecret());
  const { pathname } = req.nextUrl;

  if (pathname === "/login") {
    return session ? NextResponse.redirect(new URL("/", req.url)) : NextResponse.next();
  }
  if (!session) {
    if (pathname.startsWith("/api/")) return NextResponse.json({ error: "unauthorized" }, { status: 401 });
    const url = new URL("/login", req.url);
    if (pathname !== "/") url.searchParams.set("next", pathname);
    return NextResponse.redirect(url);
  }
  return NextResponse.next();
}

export const config = {
  matcher: ["/((?!_next/static|_next/image|favicon.ico|icon.svg|api/health).*)"],
};
