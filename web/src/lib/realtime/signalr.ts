import { HubConnectionBuilder, HubConnectionState, LogLevel } from "@microsoft/signalr";
import type { Alarm, RealtimeEvent, TelemetryPoint } from "@/lib/domain";
import type { RealtimeTransport, TransportOptions } from "./transport";

/**
 * SignalR transport for the production backend (ASP.NET Core hub behind Azure SignalR Service).
 *
 * Hub contract expected from the server:
 *   invoke  Subscribe(craneIds: string[]) -> Snapshot   (server applies tenant/RBAC filtering)
 *   on      "telemetry" (points: TelemetryPoint[])
 *   on      "alarm"     (alarm: Alarm)
 * Auth: the access token is fetched from /api/realtime/token (Entra ID in production).
 */
export function createSignalRTransport(hubUrl: string, { craneIds = [] }: TransportOptions): RealtimeTransport {
  return {
    connect(onEvent, onState) {
      const connection = new HubConnectionBuilder()
        .withUrl(hubUrl, {
          accessTokenFactory: async () => {
            const res = await fetch("/api/realtime/token", { credentials: "include" });
            return res.ok ? ((await res.json()) as { token: string }).token : "";
          },
        })
        .withAutomaticReconnect([0, 2_000, 5_000, 10_000, 30_000])
        .configureLogging(LogLevel.Warning)
        .build();

      const subscribe = async () => {
        const snapshot = await connection.invoke<Extract<RealtimeEvent, { type: "snapshot" }>>("Subscribe", craneIds);
        onEvent({ ...snapshot, type: "snapshot" });
      };

      connection.on("telemetry", (points: TelemetryPoint[]) => onEvent({ type: "telemetry", points }));
      connection.on("alarm", (alarm: Alarm) => onEvent({ type: "alarm", alarm }));
      connection.onreconnecting(() => onState("reconnecting"));
      // Groups are lost on reconnect, so re-subscribe and take a fresh snapshot.
      connection.onreconnected(() => {
        onState("open");
        void subscribe();
      });
      connection.onclose(() => onState("closed"));

      onState("connecting");
      connection
        .start()
        .then(subscribe)
        .then(() => onState("open"))
        .catch(() => onState("closed"));

      return () => {
        if (connection.state !== HubConnectionState.Disconnected) void connection.stop();
      };
    },
  };
}
