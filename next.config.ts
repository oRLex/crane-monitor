import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  reactStrictMode: true,
  poweredByHeader: false,
  // NEXT_OUTPUT=standalone (set in the Dockerfile) keeps the Docker image small for Azure App Service / Container Apps.
  output: process.env.NEXT_OUTPUT === "standalone" ? "standalone" : undefined,
  experimental: {
    // React 19 Server Actions: limit payloads, only same-origin by default.
    serverActions: { bodySizeLimit: "256kb" },
  },
};

export default nextConfig;
