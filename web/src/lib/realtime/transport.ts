import type { RealtimeEvent } from "@/lib/domain";

export type ConnectionState = "connecting" | "open" | "reconnecting" | "closed";

export interface RealtimeTransport {
  connect(onEvent: (e: RealtimeEvent) => void, onState: (s: ConnectionState) => void): () => void;
}

export interface TransportOptions {
  /** Private channels to join: "tenant.{id}" for customers, "platform" for tenants:all roles. */
  channels: string[];
}

export async function createTransport(options: TransportOptions): Promise<RealtimeTransport> {
  const { createReverbTransport } = await import("./reverb");
  return createReverbTransport(options);
}
