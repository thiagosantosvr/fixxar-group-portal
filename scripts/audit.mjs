import { readFileSync, existsSync } from "node:fs";
import { resolve } from "node:path";

const required = ["index.html","package.json","src/main.tsx","src/App.tsx","src/data.ts","src/styles.css","public/robots.txt","public/sitemap.xml"];
const missing = required.filter((file) => !existsSync(resolve(file)));
if (missing.length) {
  console.error("Arquivos obrigatórios ausentes:", missing.join(", "));
  process.exit(1);
}
const html = readFileSync("index.html","utf8");
const checks = [
  ["idioma pt-BR", /<html lang="pt-BR">/.test(html)],
  ["viewport", /name="viewport"/.test(html)],
  ["descrição SEO", /name="description"/.test(html)],
  ["canonical", /rel="canonical"/.test(html)],
  ["Open Graph", /property="og:title"/.test(html)],
  ["Twitter card", /name="twitter:card"/.test(html)],
  ["JSON-LD", /application\/ld\+json/.test(html)]
];
let failed = false;
for (const [label, ok] of checks) {
  console.log(`${ok ? "✓" : "✗"} ${label}`);
  if (!ok) failed = true;
}
if (failed) process.exit(1);
console.log(`Auditoria estática concluída. ${required.length} arquivos essenciais presentes.`);