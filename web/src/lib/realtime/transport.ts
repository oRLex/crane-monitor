import type { RealtimeEvent } from "@/lib/domain";

export type ConnectionState = "connecting" | "open" | "reconnecting" | "closed";

export interface RealtimeTransport {
  connect(onEvent: (e: RealtimeEvent) => void, onState: (s: ConnectionState) => void): () => void;
}

export interface TransportOptions {
  craneIds?: string[];
}

/** Picks the transport from NEXT_PUBLIC_REALTIME ("sse" | "signalr"). */
export async function createTransport(options: TransportOptions = {}): Promise<RealtimeTransport> {
  if (process.env.NEXT_PUBLIC_REALTIME === "signalr") {
    const { createSignalRTransport } = await import("./signalr");
    return createSignalRTransport(process.env.NEXT_PUBLIC_SIGNALR_HUB_URL ?? "/hubs/telemetry", options);
  }
  const { createSseTransport } = await import("./sse");
  return createSseTransport(options);
}
