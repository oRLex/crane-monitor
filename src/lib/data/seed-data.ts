import type { Crane, Site, Tenant, User } from "@/lib/domain";

// Public HLS test streams stand in for on-site camera feeds (Azure Media / edge gateway in production).
const STREAM_A = "https://test-streams.mux.dev/x36xhzz/x36xhzz.m3u8";
const STREAM_B = "https://demo.unified-streaming.com/k8s/features/stable/video/tears-of-steel/tears-of-steel.ism/.m3u8";

export const tenants: Tenant[] = [
  { id: "t-budinvest", name: "BudInvest Group" },
  { id: "t-skyline", name: "Skyline Construction" },
];

export const sites: Site[] = [
  { id: "s-podil", name: "Podil Residence", city: "Kyiv", tenantId: "t-budinvest" },
  { id: "s-obolon", name: "Obolon Business Park", city: "Kyiv", tenantId: "t-budinvest" },
  { id: "s-warsaw", name: "Wola Tower", city: "Warsaw", tenantId: "t-skyline" },
];

export const cranes: Crane[] = [
  { id: "c-101", name: "TC-101", model: "Flat-top 172", serialNumber: "FT172-0042", maxLoadT: 8, jibLengthM: 60, ratedMomentTm: 172, streamUrl: STREAM_A, siteId: "s-podil", tenantId: "t-budinvest" },
  { id: "c-102", name: "TC-102", model: "Hammerhead 280", serialNumber: "HH280-0107", maxLoadT: 12, jibLengthM: 65, ratedMomentTm: 280, streamUrl: STREAM_B, siteId: "s-podil", tenantId: "t-budinvest" },
  { id: "c-103", name: "TC-103", model: "Luffing 357", serialNumber: "LJ357-0011", maxLoadT: 20, jibLengthM: 55, ratedMomentTm: 357, streamUrl: null, siteId: "s-obolon", tenantId: "t-budinvest" },
  { id: "c-201", name: "WT-201", model: "Flat-top 172", serialNumber: "FT172-0088", maxLoadT: 8, jibLengthM: 60, ratedMomentTm: 172, streamUrl: STREAM_A, siteId: "s-warsaw", tenantId: "t-skyline" },
  { id: "c-202", name: "WT-202", model: "Hammerhead 280", serialNumber: "HH280-0150", maxLoadT: 12, jibLengthM: 65, ratedMomentTm: 280, streamUrl: STREAM_B, siteId: "s-warsaw", tenantId: "t-skyline" },
];

export const users: User[] = [
  { id: "u-admin", email: "admin@demo.io", name: "Olena Admin", role: "admin", tenantId: null },
  { id: "u-op", email: "operator@demo.io", name: "Taras Operator", role: "operator", tenantId: null },
  { id: "u-bud-mgr", email: "manager@budinvest.demo", name: "Iryna (BudInvest)", role: "customer_manager", tenantId: "t-budinvest" },
  { id: "u-sky-view", email: "viewer@skyline.demo", name: "Piotr (Skyline)", role: "customer_viewer", tenantId: "t-skyline" },
];
