import { describe, expect, it } from "vitest";
import { splitCitations } from "@/lib/assistant/citations";

describe("splitCitations", () => {
  it("extracts document/section ids", () => {
    expect(splitCitations("Stop at 20 m/s [D1§2], park the jib [D1§3].")).toEqual([
      { type: "text", text: "Stop at 20 m/s " },
      { type: "cite", doc: 1, section: 2, label: "[D1§2]" },
      { type: "text", text: ", park the jib " },
      { type: "cite", doc: 1, section: 3, label: "[D1§3]" },
      { type: "text", text: "." },
    ]);
  });

  it("leaves look-alikes as text", () => {
    expect(splitCitations("see [D§2] or [1]")).toEqual([{ type: "text", text: "see [D§2] or [1]" }]);
  });
});
