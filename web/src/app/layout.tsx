import type { Metadata, Viewport } from "next";
import "./globals.css";

export const metadata: Metadata = {
  title: { default: "CraneWatch", template: "%s · CraneWatch" },
  description: "Tower crane monitoring: live telemetry, video and alarms.",
};

export const viewport: Viewport = { themeColor: "#0b0e12" };

export default function RootLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="en">
      <body className="min-h-dvh">{children}</body>
    </html>
  );
}
