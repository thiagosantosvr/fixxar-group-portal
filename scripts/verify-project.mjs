import { readFileSync } from "node:fs";
const pkg = JSON.parse(readFileSync("package.json","utf8"));
const app = readFileSync("src/App.tsx","utf8");
const data = readFileSync("src/data.ts","utf8");
const universeMatch = data.match(/export const universes: Universe\[\] = (\[[\s\S]*?\]);\s*\n\s*export const pillars/s);
let universes = [];
try {
  if (universeMatch) universes = Function("return " + universeMatch[1].replace(/\bundefined\b/g, "null"))();
} catch {
  universes = [];
}
const ids = universes.map((item) => item.id);
const requirements = [
  ["nome do projeto", pkg.name === "fixxar-group-portal"],
  ["React", Boolean(pkg.dependencies?.react)],
  ["Vite", Boolean(pkg.devDependencies?.vite)],
  ["grupo institucional", app.includes("FIXXAR GROUP")],
  ["universos de marca", data.includes("universes")],
  ["pilares", data.includes("pillars")],
  ["cinco universos cadastrados", universes.length === 5],
  ["identificadores únicos", ids.length === new Set(ids).size],
  ["links ativos em HTTPS", universes.filter((item) => item.status === "active").every((item) => typeof item.url === "string" && item.url.startsWith("https://"))],
  ["universos em desenvolvimento sem URL publicada", universes.filter((item) => item.status === "in-development").every((item) => !item.url)]
];
let failed = false;
for (const [label, ok] of requirements) {
  console.log(`${ok ? "✓" : "✗"} ${label}`);
  if (!ok) failed = true;
}
if (failed) process.exit(1);
console.log("Verificação estrutural concluída.");
