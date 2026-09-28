import type { RealtimeEvent } from "@/lib/domain";
import type { RealtimeTransport, TransportOptions } from "./transport";

/** EventSource-based transport (browser reconnects automatically). */
export function createSseTransport({ craneIds = [] }: TransportOptions): RealtimeTransport {
  return {
    connect(onEvent, onState) {
      const qs = new URLSearchParams(craneIds.map((id) => ["crane", id]));
      const url = `/api/telemetry/stream${craneIds.length ? `?${qs}` : ""}`;
      onState("connecting");
      const es = new EventSource(url, { withCredentials: true });
      const handle = (e: MessageEvent<string>) => onEvent(JSON.parse(e.data) as RealtimeEvent);

      es.addEventListener("snapshot", handle);
      es.addEventListener("telemetry", handle);
      es.addEventListener("alarm", handle);
      es.onopen = () => onState("open");
      es.onerror = () => onState(es.readyState === EventSource.CLOSED ? "closed" : "reconnecting");

      return () => {
        es.close();
        onState("closed");
      };
    },
  };
}
