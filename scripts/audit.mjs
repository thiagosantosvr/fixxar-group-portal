import { readFileSync, existsSync } from "node:fs";
import { resolve } from "node:path";

const required = [
  "index.html",
  "package.json",
  "src/main.tsx",
  "src/App.tsx",
  "src/data.ts",
  "src/styles.css",
  "public/robots.txt",
  "public/sitemap.xml",
  "public/brand/fixxar-symbol-x.svg"
];
const missing = required.filter((file) => !existsSync(resolve(file)));
if (missing.length) {
  console.error("Arquivos obrigatórios ausentes:", missing.join(", "));
  process.exit(1);
}

const html = readFileSync("index.html", "utf8");
const robots = readFileSync("public/robots.txt", "utf8");
const sitemap = readFileSync("public/sitemap.xml", "utf8");
const canonical = html.match(/<link\s+rel="canonical"\s+href="([^"]+)"/)?.[1];
const icon = html.match(/<link\s+rel="icon"[^>]*href="([^"]+)"/)?.[1];
const sitemapLocation = sitemap.match(/<loc>([^<]+)<\/loc>/)?.[1];
const robotsSitemap = robots.match(/^Sitemap:\s*(\S+)/m)?.[1];

const checks = [
  ["idioma pt-BR", /<html lang="pt-BR">/.test(html)],
  ["viewport", /name="viewport"/.test(html)],
  ["descrição SEO", /name="description"/.test(html)],
  ["canonical HTTPS", Boolean(canonical && canonical.startsWith("https://"))],
  ["canonical no sitemap", Boolean(canonical && sitemapLocation === canonical)],
  ["sitemap declarado no robots.txt", Boolean(robotsSitemap && robotsSitemap.endsWith("/sitemap.xml"))],
  ["favicon declarado e existente", Boolean(icon && existsSync(resolve("public", icon.replace(/^\//, ""))))],
  ["Open Graph", /property="og:title"/.test(html)],
  ["Twitter card", /name="twitter:card"/.test(html)],
  ["JSON-LD", /application\/ld\+json/.test(html)],
  ["sem referência à imagem social inexistente", !html.includes("fixxar-group-social.png")]
];
let failed = false;
for (const [label, ok] of checks) {
  console.log(`${ok ? "✓" : "✗"} ${label}`);
  if (!ok) failed = true;
}
if (failed) process.exit(1);
console.log(`Auditoria estática concluída. ${required.length} arquivos essenciais presentes e ${checks.length} verificações aprovadas.`);
