"use client";

import { createContext, use, useEffect, useState, useSyncExternalStore } from "react";
import { TelemetryStore, initialState, type TelemetryState } from "@/lib/realtime/store";
import { createTransport } from "@/lib/realtime/transport";

const StoreContext = createContext<TelemetryStore | null>(null);

/**
 * Owns the single real-time connection for the dashboard.
 * The server passes only crane ids the principal may see; the server re-applies
 * the same filter on its side, so this is a UX hint, not a security boundary.
 */
export function TelemetryProvider({ craneIds, children }: { craneIds: string[]; children: React.ReactNode }) {
  const [store] = useState(() => new TelemetryStore());
  const key = craneIds.join(",");

  useEffect(() => {
    let disconnect: (() => void) | undefined;
    let cancelled = false;
    void createTransport({ craneIds: key ? key.split(",") : [] }).then((transport) => {
      if (!cancelled) disconnect = transport.connect(store.dispatch, store.setConnection);
    });
    return () => {
      cancelled = true;
      disconnect?.();
    };
  }, [key, store]);

  return <StoreContext value={store}>{children}</StoreContext>;
}

const serverSnapshot = () => initialState;

/** Subscribe to a slice of telemetry state. Selectors must return stable references. */
export function useTelemetry<T>(selector: (s: TelemetryState) => T): T {
  const store = use(StoreContext);
  if (!store) throw new Error("useTelemetry must be used inside <TelemetryProvider>");
  return useSyncExternalStore(
    store.subscribe,
    () => selector(store.getSnapshot()),
    () => selector(serverSnapshot()),
  );
}
