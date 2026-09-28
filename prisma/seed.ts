import { PrismaClient } from "@prisma/client";
import { cranes, sites, tenants, users } from "../src/lib/data/seed-data";

const prisma = new PrismaClient();

async function main() {
  for (const t of tenants) await prisma.tenant.upsert({ where: { id: t.id }, update: t, create: t });
  for (const s of sites) await prisma.site.upsert({ where: { id: s.id }, update: s, create: s });
  for (const c of cranes) await prisma.crane.upsert({ where: { id: c.id }, update: c, create: c });
  for (const u of users) await prisma.user.upsert({ where: { id: u.id }, update: u, create: u });
  console.log(`Seeded ${tenants.length} tenants, ${sites.length} sites, ${cranes.length} cranes, ${users.length} users`);
}

main()
  .catch((e) => {
    console.error(e);
    process.exit(1);
  })
  .finally(() => prisma.$disconnect());
