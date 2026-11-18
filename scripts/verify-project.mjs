import { readFileSync } from "node:fs";

const pkg = JSON.parse(readFileSync("package.json", "utf8"));
const app = readFileSync("src/App.tsx", "utf8");
const data = readFileSync("src/data.ts", "utf8");
const universeSource = data.match(/export const universes:\s*Universe\[\]\s*=\s*\[([\s\S]*?)\];/)?.[1] ?? "";
const universeEntries = [...universeSource.matchAll(/\{([^{}]*)\}/g)].map((match) => match[1]);
const universeIds = universeEntries.map((entry) => entry.match(/\bid:\s*"([^"]+)"/)?.[1]).filter(Boolean);
const activeEntries = universeEntries.filter((entry) => /\bstatus:\s*"active"/.test(entry));
const developmentEntries = universeEntries.filter((entry) => /\bstatus:\s*"in-development"/.test(entry));

const requirements = [
  ["nome do projeto", pkg.name === "fixxar-group-portal"],
  ["React", Boolean(pkg.dependencies?.react)],
  ["Vite", Boolean(pkg.devDependencies?.vite)],
  ["grupo institucional", app.includes("FIXXAR GROUP")],
  ["universos de marca", universeEntries.length > 0],
  ["pilares", data.includes("export const pillars")],
  ["cinco universos cadastrados", universeIds.length === 5 && universeEntries.length === 5],
  ["identificadores únicos", universeIds.length === new Set(universeIds).size],
  ["links ativos em HTTPS", activeEntries.length === 2 && activeEntries.every((entry) => /\burl:\s*"https:\/\//.test(entry))],
  ["universos em desenvolvimento sem URL publicada", developmentEntries.length === 3 && developmentEntries.every((entry) => !/\burl:/.test(entry))]
];

let failed = false;
for (const [label, ok] of requirements) {
  console.log(`${ok ? "✓" : "✗"} ${label}`);
  if (!ok) failed = true;
}
if (failed) process.exit(1);
console.log("Verificação estrutural concluída.");
