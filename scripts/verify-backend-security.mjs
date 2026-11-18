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
  ["configurações limitadas a chaves conhecidas", api.includes("$allowedSettings = ['site_name','contact_email','seo_title','seo_description','maintenance']")],
  ["e-mail de contato validado", api.includes("filter_var($value,FILTER_VALIDATE_EMAIL)")],
  ["publicação repetida de página é idempotente", api.includes("SELECT id FROM content_pages WHERE slug=? LIMIT 1")],
  ["publicação repetida da home é idempotente", api.includes("SELECT id FROM content_pages WHERE slug='home' LIMIT 1")],
  ["atualização repetida de mensagem é idempotente", api.includes("SELECT id FROM contact_messages WHERE id=? LIMIT 1")],
  ["caixa de demonstração sem contatos fictícios", adminUi.includes("const initialMessages: Message[] = [];")],
  ["indicador de páginas calculado pelo CMS", adminUi.includes("String(institutionalPages.length+1)")]
];

let failed = false;
for (const [label, ok] of checks) {
  console.log(`${ok ? "✓" : "✗"} ${label}`);
  if (!ok) failed = true;
}
if (failed) process.exit(1);
console.log(`Auditoria estática de segurança: ${checks.length} verificações aprovadas.`);
