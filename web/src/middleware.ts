import { NextResponse, type NextRequest } from "next/server";
import { TOKEN_COOKIE } from "@/lib/api/session";

/**
 * Edge gate: without a token cookie, pages redirect to /login. Whether the token is valid
 * and what it may do is decided by the Laravel API on every call.
 */
export function middleware(req: NextRequest) {
  const hasToken = Boolean(req.cookies.get(TOKEN_COOKIE)?.value);
  const { pathname } = req.nextUrl;

  if (pathname === "/login") return hasToken ? NextResponse.redirect(new URL("/", req.url)) : NextResponse.next();
  if (!hasToken) {
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
