import { readFileSync } from "node:fs";

const pkg = JSON.parse(readFileSync("package.json", "utf8"));
const app = readFileSync("src/App.tsx", "utf8");
const data = readFileSync("src/data.ts", "utf8");
const admin = readFileSync("src/Admin.tsx", "utf8");

const bootstrapChecks = [
  ["estado de carregamento explícito do bootstrap", /bootstrapState,setBootstrapState/.test(admin)],
  ["falha do bootstrap não injeta dados fictícios", /setBootstrapState\("error"\)/.test(admin) && admin.includes("Os dados não foram substituídos por exemplos.")],
  ["interface apresenta erro e opção de nova tentativa", admin.includes("Não foi possível carregar os dados oficiais.") && admin.includes("Tentar novamente")],
  ["nova tentativa usa a mesma rotina de carregamento", /onClick=\{\(\)=>void refreshAdminData\(\)\}/.test(admin)]
];
for (const [label, ok] of bootstrapChecks) {
  console.log(`${ok ? "✓" : "✗"} ${label}`);
  if (!ok) process.exitCode = 1;
}

const demoIsolationChecks = [
  ["universos demo não aparecem antes do bootstrap em sessão real", /useState<Universe\[]>\(\(\)=>live\?\[\]:readSaved\("fixxar-admin-universes",universes\)\)/.test(admin)],
  ["mensagens locais não contaminam a sessão real", /useState<Message\[]>\(\(\)=>live\?\[\]:readSaved\("fixxar-admin-messages",initialMessages\)\)/.test(admin)],
  ["rascunho local da página inicial não é carregado no ambiente real", /useState\(\(\)=>readHomeDraft\(!live\)\)/.test(admin) && /const saved=useLocal\?readSaved/.test(admin)],
  ["configurações locais não são reaproveitadas no ambiente real", /useState\(\(\)=>live\?\{siteName:/.test(admin)]
];
for (const [label, ok] of demoIsolationChecks) {
  console.log(`${ok ? "✓" : "✗"} ${label}`);
  if (!ok) process.exitCode = 1;
}

const universeSource = data.match(/export const universes:\s*Universe\[\]\s*=\s*\[([\s\S]*?)\];/)?.[1] ?? "";
const universeEntries = [...universeSource.matchAll(/\{([^{}]*)\}/g)].map((match) => match[1]);
const universeIds = universeEntries.map((entry) => entry.match(/\bid:\s*"([^"]+)"/)?.[1]).filter(Boolean);
const activeEntries = universeEntries.filter((entry) => /\bstatus:\s*"active"/.test(entry));
const developmentEntries = universeEntries.filter((entry) => /\bstatus:\s*"in-development"/.test(entry));

const textExportChecks = [
  ["slug institucional remove diacríticos após normalização Unicode", admin.includes('title.normalize("NFD").replace(/[\\u0300-\\u036f]/g,"")')],
  ["exportação CSV usa BOM Unicode real", admin.includes('const csv="\\uFEFF"')],
  ["exportação CSV usa quebras de linha reais", admin.includes('.join("\\r\\n")')]
];
const uploadAltSource = readFileSync("src/adminApi.ts", "utf8");
const uploadAltCheck = [
  ["nome alternativo de imagem remove extensão corretamente", uploadAltSource.includes('file.name.replace(/\\.[^.]+$/')]
];
for (const [label, ok] of [...textExportChecks, ...uploadAltCheck]) {
  console.log(`${ok ? "✓" : "✗"} ${label}`);
  if (!ok) process.exitCode = 1;
}

const requirements = [
  ["nome do projeto", pkg.name === "fixxar-group-portal"],
  ["React", Boolean(pkg.dependencies?.react)],
  ["Vite", Boolean(pkg.devDependencies?.vite)],
  ["grupo institucional", app.includes("FIXXAR GROUP")],
  ["universos de marca", universeEntries.length > 0],
  ["pilares", data.includes("export const pillars")],
  ["sete universos cadastrados incluindo YAD e ST SEG", universeIds.length === 7 && universeEntries.length === 7 && universeIds.includes("yad-sistemas-integrados") && universeIds.includes("st-seg")],
  ["identificadores únicos", universeIds.length === new Set(universeIds).size],
  ["links ativos em HTTPS", activeEntries.length === 2 && activeEntries.every((entry) => /\burl:\s*"https:\/\//.test(entry))],
  ["backup inclui páginas institucionais", admin.includes("institutionalPages,")],
  ["backup inclui índice de mídia", admin.includes("mediaIndex:mediaAssets.map")],
  ["backup documenta exclusão de binários e credenciais", admin.includes("não os arquivos binários das imagens") && admin.includes("hashes de senha e tokens de sessão não são exportados")],
  ["backup identifica versão do formato", admin.includes("schemaVersion:2") && admin.includes('format:"fixxar-group-backup"')],
  ["universos em desenvolvimento sem URL publicada", developmentEntries.length === 5 && developmentEntries.every((entry) => !/\burl:/.test(entry))]
];

let failed = false;
for (const [label, ok] of requirements) {
  console.log(`${ok ? "✓" : "✗"} ${label}`);
  if (!ok) failed = true;
}
if (failed) process.exit(1);
console.log("Verificação estrutural concluída.");
