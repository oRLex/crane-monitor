import Pusher from "pusher-js";
import type { Alarm, RealtimeEvent, TelemetryPoint } from "@/lib/domain";
import type { RealtimeTransport, TransportOptions } from "./transport";

/**
 * Laravel Reverb speaks the Pusher protocol. Channel authorization goes through our
 * same-origin proxy (/api/backend/broadcasting/auth), which attaches the user's token; the
 * API decides which private channels this user may join.
 *
 * WebSocket groups don't replay what was missed while disconnected, so every (re)connect
 * starts with a fresh REST snapshot.
 */
export function createReverbTransport({ channels }: TransportOptions): RealtimeTransport {
  return {
    connect(onEvent, onState) {
      const scheme = process.env.NEXT_PUBLIC_REVERB_SCHEME ?? "https";
      const port = Number(process.env.NEXT_PUBLIC_REVERB_PORT ?? (scheme === "https" ? 443 : 80));
      const pusher = new Pusher(process.env.NEXT_PUBLIC_REVERB_KEY ?? "", {
        cluster: "",
        wsHost: process.env.NEXT_PUBLIC_REVERB_HOST ?? location.hostname,
        wsPort: port,
        wssPort: port,
        forceTLS: scheme === "https",
        enabledTransports: ["ws", "wss"],
        channelAuthorization: {
          endpoint: "/api/backend/broadcasting/auth",
          transport: "ajax",
          customHandler: ({ socketId, channelName }, callback) => {
            fetch("/api/backend/broadcasting/auth", {
              method: "POST",
              headers: { "Content-Type": "application/json", Accept: "application/json" },
              body: JSON.stringify({ socket_id: socketId, channel_name: channelName }),
            })
              .then(async (r) => (r.ok ? callback(null, await r.json()) : callback(new Error(`auth ${r.status}`), null)))
              .catch((e: Error) => callback(e, null));
          },
        },
      });

      let disposed = false;
      const snapshot = async () => {
        const res = await fetch("/api/backend/realtime/snapshot", { headers: { Accept: "application/json" } });
        if (res.ok && !disposed) onEvent({ type: "snapshot", ...(await res.json()) });
      };

      onState("connecting");
      let everConnected = false;
      pusher.connection.bind("state_change", ({ current }: { current: string }) => {
        if (current === "connected") {
          onState("open");
          void snapshot();
          everConnected = true;
        } else if (current === "connecting" || current === "unavailable") {
          onState(everConnected ? "reconnecting" : "connecting");
        } else if (current === "failed" || current === "disconnected") {
          onState("closed");
        }
      });

      for (const name of channels) {
        const channel = pusher.subscribe(`private-${name}`);
        channel.bind("telemetry", (d: { points: TelemetryPoint[] }) => onEvent({ type: "telemetry", points: d.points }));
        channel.bind("alarm", (d: { alarm: Alarm }) => onEvent({ type: "alarm", alarm: d.alarm }));
        // Not part of the telemetry store; pages that care (reports) listen for this DOM event.
        channel.bind("report", (d: unknown) => window.dispatchEvent(new CustomEvent("cranewatch:report", { detail: d })));
      }

      return () => {
        disposed = true;
        pusher.disconnect();
        onState("closed");
      };
    },
  };
}
