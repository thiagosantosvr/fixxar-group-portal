import { readFileSync } from "node:fs";

const api = readFileSync("server/api/index.php", "utf8");
const schema = readFileSync("server/database/schema.sql", "utf8");
const migration = readFileSync("server/database/migrations/002_login_throttle_index.sql", "utf8");
const admin = readFileSync("server/bin/create-admin.php", "utf8");
const config = readFileSync("server/config.example.php", "utf8");
const adminUi = readFileSync("src/Admin.tsx", "utf8");

const checks = [
  ["sessão com strict mode", api.includes("session.use_strict_mode', '1'")],
  ["sessão aceita somente cookies", api.includes("session.use_only_cookies', '1'")],
  ["transporte SID desabilitado", api.includes("session.use_trans_sid', '0'")],
  ["cookie HttpOnly e SameSite Strict", api.includes("'httponly'=>true") && api.includes("'samesite'=>'Strict'")],
  ["regeneração de sessão no login", api.includes("session_regenerate_id(true)")],
  ["CSRF obrigatório nas alterações", api.includes("function requireCsrf()") && api.includes("hash_equals($known, $provided)")],
  ["bloqueio de login por 15 minutos", api.includes("INTERVAL 15 MINUTE") && api.includes("Retry-After: 900")],
  ["índice de consulta de bloqueio no schema", schema.includes("idx_audit_login_throttle (action, ip_hash, created_at)")],
  ["migração para bancos existentes", migration.includes("ADD INDEX idx_audit_login_throttle")],
  ["senhas Argon2id", api.includes("PASSWORD_ARGON2ID") && admin.includes("PASSWORD_ARGON2ID")],
  ["criação inicial restrita a CLI", admin.includes("PHP_SAPI !== 'cli'")],
  ["chave de segurança não padrão no modelo", config.includes("REPLACE_WITH_AT_LEAST_64_RANDOM_HEX_CHARACTERS")],
  ["configurações limitadas a chaves conhecidas", api.includes("$allowedSettings = ['site_name','contact_email','contact_phone','whatsapp_url','whatsapp_message','instagram_url','facebook_url','youtube_url','linkedin_url','contact_address','footer_text','seo_title','seo_description','maintenance']")],
  ["e-mail de contato validado", api.includes("filter_var($value,FILTER_VALIDATE_EMAIL)")],
  ["perfil individual de marca validado", api.includes("$profile = [];") && api.includes("invalid_image_url") && api.includes("invalid_https_url") && api.includes("invalid_email")],
  ["migração incremental de perfis de marca", readFileSync("server/database/migrations/2026-11-19-brand-profiles.sql", "utf8").includes("ALTER TABLE brand_universes")],
  ["publicação repetida de página é idempotente", api.includes("SELECT id FROM content_pages WHERE slug=? LIMIT 1")],
  ["publicação repetida da home é idempotente", api.includes("SELECT id FROM content_pages WHERE slug='home' LIMIT 1")],
  ["atualização repetida de mensagem é idempotente", api.includes("SELECT id FROM contact_messages WHERE id=? LIMIT 1")],
  ["proteção transacional do último proprietário", api.includes("SELECT id,name,email,role,is_active FROM admin_users WHERE id=? FOR UPDATE") && api.includes("SELECT id FROM admin_users WHERE role='owner' AND is_active=1 FOR UPDATE") && api.includes("$pdo->beginTransaction();")],
  ["imagem validada por MIME e assinatura", api.includes("getimagesize($upload['tmp_name'])") && api.includes("($imageInfo['mime'] ?? '') !== $mime")],
  ["limite contra imagens com dimensões excessivas", api.includes("> 40000000")],
  ["upload transacional com limpeza em falha", api.includes("$pdo->beginTransaction();") && api.includes("if (is_file($destination)) unlink($destination)")],
  ["exclusão de mídia com restauração em rollback", api.includes("$tombstone=$directory.'/'.bin2hex(random_bytes(16)).'.delete'") && api.includes("rename($tombstone,$file)")],
  ["bootstrap do suporte limita dados à caixa de entrada", /if \(\$admin\['role'\] === 'support'\)[\s\S]*?'pages'=>\[\][\s\S]*?'universes'=>\[\][\s\S]*?'settings'=>\[\][\s\S]*?'media'=>\[\]/.test(api)],
  ["bootstrap administrativo carrega CMS somente após filtro de suporte", api.indexOf("if ($admin['role'] === 'support')") < api.indexOf("$pages = $pdo->query('SELECT slug,title,content_json,status,updated_at FROM content_pages")],
  ["interface do suporte abre diretamente na caixa de entrada", adminUi.includes('useState<Section>(live&&user?.role==="support"?"messages":"overview")')],
  ["interface do suporte oculta seções fora da sua função", adminUi.includes('user?.role!=="support"||item.id==="messages"')],
  ["consulta de contas administrativas exclusiva do proprietário", api.includes("requireRole($admin, ['owner']);") && api.includes("if ($path === '/admin/users' && $method === 'GET')")],
  ["exportação de backup indisponível para suporte", adminUi.includes('(!live||user?.role!=="support")&&<button className="admin-primary" onClick={exportBackup}')],
  ["caixa de demonstração sem contatos fictícios", adminUi.includes("const initialMessages: Message[] = [];")],
  ["indicador de páginas calculado pelo CMS", adminUi.includes("String(institutionalPages.length+1)")]
];

const cmsAtomicityChecks = [
  ["salvar página institucional e auditoria na mesma transação", /\$pdo->beginTransaction\(\);[\s\S]*?INSERT INTO content_pages \(slug,title,content_json,status,updated_by\)[\s\S]*?audit\(\$pdo,\$admin,'page\.save_draft','page',\$slug\);[\s\S]*?\$pdo->commit\(\);/.test(api)],
  ["publicar página institucional com lock e auditoria atômica", /SELECT id FROM content_pages WHERE slug=\? LIMIT 1 FOR UPDATE[\s\S]*?audit\(\$pdo,\$admin,'page\.publish','page',\$slug\);[\s\S]*?\$pdo->commit\(\);/.test(api)],
  ["salvar página inicial e auditoria na mesma transação", /INSERT INTO content_pages \(slug,title,content_json,status,updated_by\) VALUES \('home'[\s\S]*?audit\(\$pdo, \$admin, 'page\.save_draft', 'page', 'home'\);[\s\S]*?\$pdo->commit\(\);/.test(api)],
  ["publicar página inicial com lock e auditoria atômica", /SELECT id FROM content_pages WHERE slug='home' LIMIT 1 FOR UPDATE[\s\S]*?audit\(\$pdo, \$admin, 'page\.publish', 'page', 'home'\);[\s\S]*?\$pdo->commit\(\);/.test(api)],
  ["transações do CMS desfazem alterações em falha", (api.match(/if \(\$pdo->inTransaction\(\)\) \$pdo->rollBack\(\);/g)||[]).length >= 6]
];
for (const [label, ok] of cmsAtomicityChecks) {
  console.log(`${ok ? "✓" : "✗"} ${label}`);
  if (!ok) process.exitCode = 1;
}

let failed = false;
for (const [label, ok] of checks) {
  console.log(`${ok ? "✓" : "✗"} ${label}`);
  if (!ok) failed = true;
}
if (failed) process.exit(1);
console.log(`Auditoria estática de segurança: ${checks.length} verificações aprovadas.`);
