import { readFileSync } from "node:fs";

const app = readFileSync("src/App.tsx", "utf8");
const page = readFileSync("src/InstitutionalPage.tsx", "utf8");
const api = readFileSync("server/api/index.php", "utf8");
const rewrite = readFileSync("public/.htaccess", "utf8");
const readme = readFileSync("server/README.md", "utf8");

const checks = [
  ["rota institucional usa slug codificado", page.includes("encodeURIComponent(slug)")],
  ["frontend consulta endpoint público", page.includes("/api/public/page?slug=")],
  ["frontend trata página inexistente", page.includes('response.status === 404') && page.includes('setState("missing")')],
  ["frontend evita atualizar estado após desmontagem", page.includes("return () => { active = false; }")],
  ["frontend lista apenas páginas retornadas pela API pública", app.includes("setPublishedPages(data.pages || [])")],
  ["links institucionais entram na navegação", app.includes("publishedPages.map")],
  ["API consulta somente páginas publicadas", /slug=\? AND status='published'/.test(api)],
  ["API publica lista sem incluir home", /status='published' AND slug<>'home'/.test(api)],
  ["API valida slug e bloqueia home", /\^\[a-z0-9-\]\{2,100\}\$/.test(api) && /\$slug === 'home'/.test(api)],
  ["API protege slugs reservados", api.includes("reserved_page_slug") && ["api","painel","assets","storage","server"].every(s => api.includes(`'${s}'`))],
  ["API não retorna rascunhos", /status='published'/.test(api)],
  ["Apache encaminha API antes do fallback SPA", rewrite.indexOf("RewriteRule ^api") < rewrite.indexOf("RewriteRule ^ index.html")],
  ["documentação descreve a rota pública", readme.includes("Páginas institucionais públicas") && readme.includes("GET /api/public/page?slug={slug}")]
];

let failed = false;
for (const [label, ok] of checks) {
  console.log(`${ok ? "✓" : "✗"} ${label}`);
  if (!ok) failed = true;
}
if (failed) process.exit(1);
console.log(`Contrato de páginas públicas validado: ${checks.length} verificações aprovadas.`);
