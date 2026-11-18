import { readFileSync } from "node:fs";

const pkg = JSON.parse(readFileSync("package.json", "utf8"));
const app = readFileSync("src/App.tsx", "utf8");
const data = readFileSync("src/data.ts", "utf8");
const universeIds = [...data.matchAll(/\bid:\s*"([^"]+)"/g)].map((match) => match[1]);
const universeEntries = [...data.matchAll(/\{[^{}]*status:\s*"(?:active|in-development)"[^{}]*\}/g)].map((match) => match[0]);
const activeEntries = universeEntries.filter((entry) => /status:\s*"active"/.test(entry));
const developmentEntries = universeEntries.filter((entry) => /status:\s*"in-development"/.test(entry));
const requirements = [
  ["nome do projeto", pkg.name === "fixxar-group-portal"],
  ["React", Boolean(pkg.dependencies?.react)],
  ["Vite", Boolean(pkg.devDependencies?.vite)],
  ["grupo institucional", app.includes("FIXXAR GROUP")],
  ["universos de marca", data.includes("universes")],
  ["pilares", data.includes("pillars")],
  ["cinco universos cadastrados", universeIds.length === 5 && universeEntries.length === 5],
  ["identificadores únicos", universeIds.length === new Set(universeIds).size],
  ["links ativos em HTTPS", activeEntries.length > 0 && activeEntries.every((entry) => /url:\s*"https:\/\//.test(entry))],
  ["universos em desenvolvimento sem URL publicada", developmentEntries.length > 0 && developmentEntries.every((entry) => !/\burl:/.test(entry))]
];
let failed = false;
for (const [label, ok] of requirements) {
  console.log(`${ok ? "✓" : "✗"} ${label}`);
  if (!ok) failed = true;
}
if (failed) process.exit(1);
console.log("Verificação estrutural concluída.");
