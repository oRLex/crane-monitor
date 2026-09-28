"use client";

import { useEffect, useRef, useState } from "react";
import type Hls from "hls.js";

type PlayerState = "loading" | "playing" | "error";

/**
 * Live HLS player for on-site cameras.
 * - Safari / iOS: native HLS via <video src>.
 * - Everything else: hls.js (lazy-loaded, ~130 kB kept out of the main bundle).
 * - Recovers from transient network/media errors, shows a retry on fatal ones.
 */
export function HlsPlayer({ src, title }: { src: string; title: string }) {
  const videoRef = useRef<HTMLVideoElement>(null);
  const [state, setState] = useState<PlayerState>("loading");
  const [attempt, setAttempt] = useState(0);

  useEffect(() => {
    const video = videoRef.current;
    if (!video) return;
    let hls: Hls | undefined;
    let cancelled = false;
    setState("loading");

    const onPlaying = () => setState("playing");
    video.addEventListener("playing", onPlaying);

    if (video.canPlayType("application/vnd.apple.mpegurl")) {
      video.src = src;
      video.play().catch(() => {});
    } else {
      void import("hls.js").then(({ default: HlsCtor }) => {
        if (cancelled) return;
        if (!HlsCtor.isSupported()) return setState("error");
        hls = new HlsCtor({ lowLatencyMode: true, backBufferLength: 30, maxBufferLength: 20 });
        hls.on(HlsCtor.Events.ERROR, (_e, data) => {
          if (!data.fatal) return;
          if (data.type === HlsCtor.ErrorTypes.NETWORK_ERROR) hls?.startLoad();
          else if (data.type === HlsCtor.ErrorTypes.MEDIA_ERROR) hls?.recoverMediaError();
          else setState("error");
        });
        hls.on(HlsCtor.Events.MANIFEST_PARSED, () => void video.play().catch(() => {}));
        hls.loadSource(src);
        hls.attachMedia(video);
      });
    }

    return () => {
      cancelled = true;
      video.removeEventListener("playing", onPlaying);
      hls?.destroy();
      video.removeAttribute("src");
      video.load();
    };
  }, [src, attempt]);

  return (
    <div className="relative aspect-video overflow-hidden rounded-lg bg-black">
      <video ref={videoRef} className="size-full object-contain" muted playsInline controls aria-label={title} />
      <div className="pointer-events-none absolute left-3 top-3 flex items-center gap-2">
        <span className="rounded bg-crit px-1.5 py-0.5 text-[10px] font-bold tracking-wider text-white">LIVE</span>
        <span className="rounded bg-black/60 px-1.5 py-0.5 text-xs text-ink">{title}</span>
      </div>
      {state === "loading" && (
        <div className="absolute inset-0 grid place-items-center text-sm text-muted">Connecting to camera…</div>
      )}
      {state === "error" && (
        <div className="absolute inset-0 grid place-items-center gap-3 bg-black/80 text-center text-sm">
          <div>
            <p className="mb-3 text-muted">Video stream unavailable</p>
            <button
              type="button"
              onClick={() => setAttempt((a) => a + 1)}
              className="rounded-md border border-line px-3 py-1.5 text-ink hover:border-crane"
            >
              Retry
            </button>
          </div>
        </div>
      )}
    </div>
  );
}
