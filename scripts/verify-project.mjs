import { readFileSync } from "node:fs";
const pkg = JSON.parse(readFileSync("package.json","utf8"));
const app = readFileSync("src/App.tsx","utf8");
const data = readFileSync("src/data.ts","utf8");
const requirements = [
  ["nome do projeto", pkg.name === "fixxar-group-portal"],
  ["React", Boolean(pkg.dependencies?.react)],
  ["Vite", Boolean(pkg.devDependencies?.vite)],
  ["grupo institucional", app.includes("FIXXAR GROUP")],
  ["universos de marca", data.includes("universes")],
  ["pilares", data.includes("pillars")]
];
let failed = false;
for (const [label, ok] of requirements) {
  console.log(`${ok ? "✓" : "✗"} ${label}`);
  if (!ok) failed = true;
}
if (failed) process.exit(1);
console.log("Verificação estrutural concluída.");
