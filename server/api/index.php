<?php
declare(strict_types=1);

// Buffer accidental output from included configuration files before HTTP headers/session startup.
ob_start();

/**
 * FIXXAR GROUP API — PHP 8.1+, PDO MySQL.
 * Configure server/config.php from config.example.php before enabling.
 */
$configPath = dirname(__DIR__) . '/config.php';
if (!is_file($configPath)) {
    http_response_code(503);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'api_not_configured', 'message' => 'A API ainda não foi configurada no servidor.']);
    exit;
}
$config = require $configPath;
// Configuration files must not emit bytes before session_start(); discard any incidental output.
if (ob_get_level() > 0) { ob_clean(); }
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, private');
header('Vary: Origin');

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '' && hash_equals((string)$config['app_origin'], $origin)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');
    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') { http_response_code(204); exit; }

function respond(int $status, array $body): never {
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function body(): array {
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') return [];
    $data = json_decode($raw, true);
    if (!is_array($data)) respond(400, ['error' => 'invalid_json']);
    return $data;
}
function db(array $config): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;
    $d = $config['database'];
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $d['host'], $d['port'] ?? 3306, $d['name'], $d['charset'] ?? 'utf8mb4');
    $pdo = new PDO($dsn, $d['user'], $d['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}
function currentAdmin(PDO $pdo): ?array {
    if (empty($_SESSION['admin_id'])) return null;
    $q = $pdo->prepare('SELECT id,name,email,role FROM admin_users WHERE id=? AND is_active=1 LIMIT 1');
    $q->execute([(int)$_SESSION['admin_id']]);
    return $q->fetch() ?: null;
}
function requireAdmin(PDO $pdo): array {
    $admin = currentAdmin($pdo);
    if (!$admin) respond(401, ['error' => 'authentication_required']);
    return $admin;
}
function requireCsrf(): void {
    $provided = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $known = $_SESSION['csrf_token'] ?? '';
    if (!is_string($provided) || !is_string($known) || $known === '' || !hash_equals($known, $provided)) {
        respond(419, ['error' => 'csrf_validation_failed']);
    }
}
function requireRole(array $admin, array $roles): void {
    if (!in_array($admin['role'], $roles, true)) respond(403, ['error' => 'insufficient_permissions']);
}
function audit(PDO $pdo, array $admin, string $action, string $type, ?string $id = null): void {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $hash = $ip !== '' ? hash_hmac('sha256', $ip, (string)($_SESSION['audit_salt'] ?? 'session')) : null;
    $q = $pdo->prepare('INSERT INTO admin_audit_log (admin_user_id,action,entity_type,entity_id,ip_hash) VALUES (?,?,?,?,?)');
    $q->execute([(int)$admin['id'], $action, $type, $id, $hash]);
}
function jsonColumn(array $row, string $key): array {
    $decoded = json_decode((string)($row[$key] ?? ''), true);
    return is_array($decoded) ? $decoded : [];
}

$secure = (bool)($config['session_secure'] ?? true);
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.use_trans_sid', '0');
session_name((string)($config['session_name'] ?? 'fixxar_admin'));
session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']);
$sessionStartWarning = false;
set_error_handler(static function (int $severity) use (&$sessionStartWarning): bool {
    $sessionStartWarning = true;
    return true;
});
$sessionStarted = session_start();
restore_error_handler();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path = preg_replace('#/+#', '/', $path);
$apiBase = rtrim((string)($config['api_base'] ?? '/api'), '/');
if (str_starts_with($path, $apiBase)) $path = substr($path, strlen($apiBase)) ?: '/';
$path = '/' . trim($path, '/');

try {
    $pdo = db($config);

    if ($path === '/health' && $method === 'GET') {
        $pdo->query('SELECT 1');
        respond(200, ['ok'=>true,'service'=>'fixxar-group-api']);
    }

    if ($path === '/auth/login' && $method === 'POST') {
        $input = body();
        $email = filter_var(strtolower(trim((string)($input['email'] ?? ''))), FILTER_VALIDATE_EMAIL);
        $password = (string)($input['password'] ?? '');
        if (!$email || $password === '' || strlen($password) > 4096) respond(422, ['error'=>'invalid_credentials']);
        $remoteIp = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $configuredSecurityKey = (string)($config['security_key'] ?? '');
        $fingerprintKey = ($configuredSecurityKey !== '' && !str_starts_with($configuredSecurityKey, 'REPLACE_'))
            ? $configuredSecurityKey
            : (string)$config['database']['password'];
        $loginFingerprint = hash_hmac('sha256', strtolower((string)$email) . '|' . $remoteIp, $fingerprintKey);
        $attempts = $pdo->prepare("SELECT COUNT(*) FROM admin_audit_log WHERE action='auth.failed' AND ip_hash=? AND created_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
        $attempts->execute([$loginFingerprint]);
        if ((int)$attempts->fetchColumn() >= 5) {
            header('Retry-After: 900');
            respond(429, ['error'=>'login_temporarily_blocked']);
        }
        $q = $pdo->prepare('SELECT id,name,email,password_hash,role FROM admin_users WHERE email=? AND is_active=1 LIMIT 1');
        $q->execute([$email]);
        $user = $q->fetch();
        if (!$user || !password_verify($password, $user['password_hash'])) {
            $failed = $pdo->prepare("INSERT INTO admin_audit_log (admin_user_id,action,entity_type,entity_id,ip_hash) VALUES (NULL,'auth.failed','admin_login',?,?)");
            $failed->execute([hash('sha256', strtolower((string)$email)), $loginFingerprint]);
            usleep(350000);
            respond(401, ['error'=>'invalid_credentials']);
        }
        $clearFailures = $pdo->prepare("DELETE FROM admin_audit_log WHERE action='auth.failed' AND ip_hash=?");
        $clearFailures->execute([$loginFingerprint]);
        session_regenerate_id(true);
        $_SESSION['admin_id'] = (int)$user['id'];
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['audit_salt'] = bin2hex(random_bytes(32));
        $pdo->prepare('UPDATE admin_users SET last_login_at=NOW() WHERE id=?')->execute([(int)$user['id']]);
        audit($pdo, $user, 'auth.login', 'admin_user', (string)$user['id']);
        unset($user['password_hash']);
        // Persist the authenticated session before returning the login response.
        session_write_close();
        respond(200, ['user'=>$user,'csrfToken'=>$_SESSION['csrf_token']]);
    }

    if ($path === '/auth/session' && $method === 'GET') {
        $admin = currentAdmin($pdo);
        if (!$admin) {
            // Safe diagnostics: expose only booleans, never cookie values, session IDs or user identifiers.
            $savePath = (string)ini_get('session.save_path');
            respond(200, [
                'authenticated' => false,
                'diagnostics' => [
                    'session_cookie_received' => isset($_COOKIE[session_name()]),
                    'session_started' => session_status() === PHP_SESSION_ACTIVE,
                    'session_start_succeeded' => $sessionStarted,
                    'session_start_warning' => $sessionStartWarning,
                    'headers_already_sent' => headers_sent(),
                    'session_name_configured' => session_name() !== '',
                    'admin_session_present' => !empty($_SESSION['admin_id']),
                    'session_storage_path_configured' => $savePath !== '',
                    'session_storage_writable' => $savePath !== '' ? is_writable($savePath) : null,
                ],
            ]);
        }
        if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        respond(200, ['authenticated'=>true,'user'=>$admin,'csrfToken'=>$_SESSION['csrf_token']]);
    }

    if ($path === '/auth/logout' && $method === 'POST') {
        $admin = requireAdmin($pdo); requireCsrf();
        audit($pdo, $admin, 'auth.logout', 'admin_user', (string)$admin['id']);
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', ['expires'=>time()-42000,'path'=>$p['path'],'secure'=>$p['secure'],'httponly'=>$p['httponly'],'samesite'=>'Strict']);
        }
        session_destroy();
        respond(200, ['ok'=>true]);
    }

    if ($path === '/contact' && $method === 'POST') {
        $length = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
        if ($length > 12000) respond(413, ['error'=>'request_too_large']);
        if (!empty($_SESSION['contact_sent_at']) && time() - (int)$_SESSION['contact_sent_at'] < 30) {
            respond(429, ['error'=>'please_wait']);
        }
        $input = body();
        // Honeypot: bots that fill the hidden field receive a neutral success response.
        if (trim((string)($input['website'] ?? '')) !== '') respond(200, ['ok'=>true]);
        $name = trim((string)($input['name'] ?? ''));
        $email = filter_var(trim((string)($input['email'] ?? '')), FILTER_VALIDATE_EMAIL);
        $subject = trim((string)($input['subject'] ?? ''));
        $message = trim((string)($input['message'] ?? ''));
        if ($name === '' || mb_strlen($name) > 160 || !$email || mb_strlen((string)$email) > 190 ||
            $subject === '' || mb_strlen($subject) > 180 || $message === '' || mb_strlen($message) > 5000) {
            respond(422, ['error'=>'invalid_contact_data']);
        }
        $q = $pdo->prepare('INSERT INTO contact_messages (name,email,subject,message) VALUES (?,?,?,?)');
        $q->execute([$name, $email, $subject, $message]);
        $_SESSION['contact_sent_at'] = time();
        respond(201, ['ok'=>true]);
    }

    if (preg_match('#^/media/([a-f0-9]{32}\\.(?:jpg|png|webp))$#', $path, $match) && $method === 'GET') {
        $q = $pdo->prepare('SELECT storage_name,mime_type FROM media_assets WHERE storage_name=? LIMIT 1');
        $q->execute([$match[1]]);
        $asset = $q->fetch();
        $file = dirname(__DIR__) . '/storage/media/' . $match[1];
        if (!$asset || !is_file($file)) respond(404, ['error'=>'media_not_found']);
        header('Content-Type: ' . $asset['mime_type']);
        header('Content-Length: ' . (string)filesize($file));
        header('Cache-Control: public, max-age=31536000, immutable');
        readfile($file);
        exit;
    }

    if ($path === '/public/page' && $method === 'GET') {
        $slug = (string)($_GET['slug'] ?? '');
        if (!preg_match('/^[a-z0-9-]{2,100}$/', $slug) || $slug === 'home') respond(404, ['error'=>'page_not_found']);
        $q = $pdo->prepare("SELECT slug,title,content_json FROM content_pages WHERE slug=? AND status='published' LIMIT 1");
        $q->execute([$slug]);
        $page = $q->fetch();
        if (!$page) respond(404, ['error'=>'page_not_found']);
        $page['content'] = jsonColumn($page, 'content_json');
        unset($page['content_json']);
        respond(200, ['page'=>$page]);
    }

    if ($path === '/public/content' && $method === 'GET') {
        $pageQuery = $pdo->prepare("SELECT title,content_json,updated_at FROM content_pages WHERE slug='home' AND status='published' LIMIT 1");
        $pageQuery->execute();
        $page = $pageQuery->fetch();
        if ($page) $page['content'] = jsonColumn($page, 'content_json');
        unset($page['content_json']);
        $universes = $pdo->query("SELECT slug,name,eyebrow,description,detail,category,website_url,status,sort_order,logo_url,logo_background,cover_image_url,contact_email,contact_phone,whatsapp_url,instagram_url,facebook_url,youtube_url,linkedin_url,contact_address FROM brand_universes WHERE status IN ('active','in-development') ORDER BY sort_order,id")->fetchAll();
        // Keep the YAD visible on the institutional portal even when an older
        // production database has not yet received the incremental YAD migration.
        $yadSlug = 'yad-sistemas-integrados';
        $hasYad = false;
        foreach ($universes as $universe) {
            if (($universe['slug'] ?? '') === $yadSlug) { $hasYad = true; break; }
        }
        if (!$hasYad) {
            $universes[] = [
                'slug' => $yadSlug,
                'name' => 'YAD Sistemas Integrados',
                'eyebrow' => 'Tecnologia & integração',
                'description' => 'Soluções integradas para conectar processos, sistemas e operações.',
                'detail' => 'Unidade de tecnologia dedicada a sistemas integrados e soluções digitais. Informações comerciais e endereço oficial serão definidos na etapa de lançamento.',
                'category' => 'Tecnologia',
                'website_url' => null,
                'status' => 'in-development',
                'sort_order' => 6,
                'logo_url' => null, 'logo_background' => 'transparent', 'cover_image_url' => null, 'contact_email' => null, 'contact_phone' => null,
                'whatsapp_url' => null, 'instagram_url' => null, 'facebook_url' => null, 'youtube_url' => null,
                'linkedin_url' => null, 'contact_address' => null,
            ];
        }

        $publishedPages = $pdo->query("SELECT slug,title FROM content_pages WHERE status='published' AND slug<>'home' ORDER BY title")->fetchAll();
        $settingQuery = $pdo->prepare("SELECT setting_key,setting_value FROM site_settings WHERE setting_key IN ('seo_title','seo_description','maintenance','site_name','contact_email','contact_phone','whatsapp_url','whatsapp_message','instagram_url','facebook_url','youtube_url','linkedin_url','contact_address','footer_text','body_text_size')");
        $settingQuery->execute();
        $seo = ['title'=>'FIXXAR GROUP — Ideias que ganham forma','description'=>'Criatividade, comunicação, conhecimento e inovação em um ecossistema de marcas.'];
        $maintenance = false;
        $publicSettings = [];
        foreach ($settingQuery->fetchAll() as $setting) {
            $value = json_decode((string)$setting['setting_value'], true);
            if ($setting['setting_key'] === 'maintenance') { $maintenance = $value === true || $value === 1 || $value === '1'; continue; }
            if (in_array($setting['setting_key'], ['seo_title','seo_description'], true)) { if (is_string($value) && trim($value) !== '') $seo[$setting['setting_key'] === 'seo_title' ? 'title' : 'description'] = trim($value); continue; }
            if (is_string($value) && trim($value) !== '') $publicSettings[$setting['setting_key']] = trim($value);
        }
        respond(200, ['page'=>$page ?: null,'pages'=>$publishedPages,'universes'=>$universes,'seo'=>$seo,'maintenance'=>$maintenance,'settings'=>$publicSettings]);
    }

    $admin = requireAdmin($pdo);

    if ($path === '/admin/backup' && $method === 'POST') {
        requireCsrf();
        requireRole($admin, ['owner']);
        if (!class_exists('ZipArchive')) respond(503, ['error'=>'backup_zip_unavailable']);
        @set_time_limit(300);
        ignore_user_abort(true);

        $root = dirname(__DIR__, 2);
        $temporaryBase = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'fixxar-backup-' . bin2hex(random_bytes(12));
        $zipPath = $temporaryBase . '.zip';
        $sqlPath = $temporaryBase . '.sql';
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            respond(500, ['error'=>'backup_archive_unavailable']);
        }

        $sql = @fopen($sqlPath, 'wb');
        if ($sql === false) {
            $zip->close();
            @unlink($zipPath);
            respond(500, ['error'=>'backup_storage_unavailable']);
        }

        $tableCount = 0;
        $rowCount = 0;
        $fileCount = 0;
        $totalMediaBytes = 0;
        try {
            audit($pdo, $admin, 'backup.complete', 'system', gmdate('Y-m-d'));
            $pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            $pdo->beginTransaction();
            fwrite($sql, "-- FIXXAR GROUP complete database backup\n-- Generated: " . gmdate('c') . "\n-- Import into an empty database; review before restoring over existing data.\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");
            $tableRows = $pdo->query('SHOW FULL TABLES')->fetchAll(PDO::FETCH_NUM);
            foreach ($tableRows as $tableInfo) {
                $tableName = (string)($tableInfo[0] ?? '');
                $tableType = (string)($tableInfo[1] ?? '');
                if ($tableName === '' || $tableType !== 'BASE TABLE') continue;
                $qmark = chr(96);
                $quotedTable = $qmark . str_replace($qmark, $qmark . $qmark, $tableName) . $qmark;
                $create = $pdo->query('SHOW CREATE TABLE ' . $quotedTable)->fetch(PDO::FETCH_NUM);
                if (!$create || !isset($create[1])) throw new RuntimeException('Unable to read table definition.');
                fwrite($sql, "-- Table: " . $tableName . "\n" . $create[1] . ";\n");
                $statement = $pdo->query('SELECT * FROM ' . $quotedTable);
                while ($row = $statement->fetch(PDO::FETCH_NUM)) {
                    $values = [];
                    foreach ($row as $value) {
                        if ($value === null) { $values[] = 'NULL'; continue; }
                        if (is_int($value) || is_float($value)) { $values[] = (string)$value; continue; }
                        $value = (string)$value;
                        if (preg_match('//u', $value) !== 1) {
                            $values[] = '0x' . bin2hex($value);
                        } else {
                            $quoted = $pdo->quote($value);
                            if ($quoted === false) throw new RuntimeException('Unable to encode a database value.');
                            $values[] = $quoted;
                        }
                    }
                    fwrite($sql, 'INSERT INTO ' . $quotedTable . ' VALUES (' . implode(',', $values) . ");\n");
                    $rowCount++;
                }
                fwrite($sql, "\n");
                $tableCount++;
            }
            fwrite($sql, "SET FOREIGN_KEY_CHECKS=1;\n");
            $pdo->commit();
            fclose($sql);
            $sql = null;
            if (!$zip->addFile($sqlPath, 'database/fixxar-group.sql')) throw new RuntimeException('Unable to add database dump.');

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::LEAVES_ONLY
            );
            foreach ($iterator as $file) {
                if (!$file->isFile() || $file->isLink()) continue;
                $absolute = $file->getPathname();
                $relative = ltrim(str_replace(DIRECTORY_SEPARATOR, '/', substr($absolute, strlen($root))), '/');
                if ($relative === '' || in_array($relative, [
                    'server/config.php',
                    'server/instalar-admin.php',
                    'server/diagnostico.php',
                    'server/teste-php.php',
                    'server/acesso-inicial.php',
                    'server/api/index_antigo.php',
                    'server/storage/owner-setup.lock',
                ], true)) continue;
                $parts = explode('/', $relative);
                if (in_array('.git', $parts, true) || in_array('node_modules', $parts, true) || in_array('vendor', $parts, true)) continue;
                if (str_starts_with($relative, 'server/storage/backups/') || str_starts_with($relative, 'server/storage/tmp/')) continue;
                if (preg_match('/(^|\/)(?:\.env(?:\..*)?|.*\.log|.*\.zip)$/i', $relative)) continue;
                if (!$zip->addFile($absolute, 'site/' . $relative)) throw new RuntimeException('Unable to add a site file to backup.');
                $fileCount++;
                if (str_starts_with($relative, 'server/storage/media/')) $totalMediaBytes += (int)$file->getSize();
            }

            $manifest = [
                'format' => 'fixxar-group-complete-backup',
                'schema_version' => 1,
                'created_at' => gmdate('c'),
                'database' => ['tables' => $tableCount, 'rows' => $rowCount, 'dump' => 'database/fixxar-group.sql'],
                'site_files' => $fileCount,
                'media_bytes' => $totalMediaBytes,
                'excluded' => ['server/config.php (credentials and secrets)', 'server/instalar-admin.php (one-time installer)', 'server/diagnostico.php and server/teste-php.php (diagnostic scripts)', 'server/acesso-inicial.php (initial owner setup)', 'server/api/index_antigo.php (legacy API)', 'server/storage/owner-setup.lock (one-time setup marker)', 'sessions, temporary files, logs, caches and prior backup archives'],
                'restore_note' => 'Restore into a separate empty database first. Reconfigure server/config.php manually from a secure copy; never overwrite production before validating the restored site.'
            ];
            $zip->addFromString('BACKUP-MANIFEST.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $zip->addFromString('RESTORE-README.txt', "FIXXAR GROUP — BACKUP COMPLETO\n\nConteúdo: dump SQL de todas as tabelas, arquivos do portal e biblioteca de mídia.\n\nIMPORTANTE: server/config.php não está incluído porque contém credenciais e segredos. Guarde uma cópia segura separadamente.\n\nRestauração: crie um banco vazio, importe database/fixxar-group.sql pelo phpMyAdmin, restaure os arquivos da pasta site/ e configure server/config.php com as credenciais corretas. Faça a restauração primeiro em homologação; não importe diretamente sobre produção.\n");
            if (!$zip->close()) throw new RuntimeException('Unable to finalize backup archive.');
            if (!is_file($zipPath) || filesize($zipPath) < 100) throw new RuntimeException('Backup archive is incomplete.');
        } catch (Throwable $backupError) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if (is_resource($sql)) fclose($sql);
            $zip->close();
            @unlink($sqlPath);
            @unlink($zipPath);
            error_log('[FIXXAR BACKUP] ' . $backupError->getMessage());
            respond(500, ['error'=>'backup_generation_failed']);
        }

        $filename = 'FIXXAR-GROUP-BACKUP-' . gmdate('Ymd-His') . '.zip';
        $size = filesize($zipPath);
        session_write_close();
        while (ob_get_level() > 0) ob_end_clean();
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . (string)$size);
        header('Cache-Control: no-store, private');
        header('X-Content-Type-Options: nosniff');
        readfile($zipPath);
        @unlink($sqlPath);
        @unlink($zipPath);
        exit;
    }

    if ($path === '/admin/users' && $method === 'GET') {
        requireRole($admin, ['owner']);
        $users = $pdo->query('SELECT id,name,email,role,is_active,last_login_at,created_at FROM admin_users ORDER BY name,id')->fetchAll();
        respond(200, ['users'=>$users]);
    }

    if ($path === '/admin/users' && $method === 'POST') {
        requireCsrf(); requireRole($admin, ['owner']);
        $input = body();
        $name = trim((string)($input['name'] ?? ''));
        $email = strtolower(trim((string)($input['email'] ?? '')));
        $password = (string)($input['password'] ?? '');
        $role = (string)($input['role'] ?? 'editor');
        if ($name === '' || mb_strlen($name) > 120 || !filter_var($email,FILTER_VALIDATE_EMAIL) || mb_strlen($email)>190 ||
            strlen($password)<14 || strlen($password)>4096 || !in_array($role,['administrator','editor','support'],true)) {
            respond(422,['error'=>'invalid_admin_user']);
        }
        $exists=$pdo->prepare('SELECT id FROM admin_users WHERE email=? LIMIT 1');
        $exists->execute([$email]);
        if ($exists->fetch()) respond(409,['error'=>'email_already_exists']);
        $hash=password_hash($password,PASSWORD_ARGON2ID);
        $q=$pdo->prepare('INSERT INTO admin_users (name,email,password_hash,role,is_active) VALUES (?,?,?,?,1)');
        $q->execute([$name,$email,$hash,$role]);
        $id=(int)$pdo->lastInsertId();
        audit($pdo,$admin,'admin_user.create','admin_user',(string)$id);
        respond(201,['ok'=>true,'user'=>['id'=>$id,'name'=>$name,'email'=>$email,'role'=>$role,'is_active'=>1,'last_login_at'=>null]]);
    }

    if (preg_match('#^/admin/users/(\\d+)$#',$path,$match) && $method === 'PATCH') {
        requireCsrf(); requireRole($admin,['owner']);
        $id=(int)$match[1];
        $input=body();
        $password=(string)($input['password']??'');
        if ($password!=='' && (strlen($password)<14 || strlen($password)>4096)) respond(422,['error'=>'invalid_admin_user']);
        try {
            $pdo->beginTransaction();
            // Lock the target and owner rows so concurrent changes cannot remove the last active owner.
            $targetQuery=$pdo->prepare('SELECT id,name,email,role,is_active FROM admin_users WHERE id=? FOR UPDATE');
            $targetQuery->execute([$id]);
            $target=$targetQuery->fetch();
            if (!$target) { $pdo->rollBack(); respond(404,['error'=>'admin_user_not_found']); }
            $name=array_key_exists('name',$input)?trim((string)$input['name']):(string)$target['name'];
            $role=(string)($input['role']??$target['role']);
            $active=array_key_exists('is_active',$input)?(int)(bool)$input['is_active']:(int)$target['is_active'];
            if ($name==='' || mb_strlen($name)>120 || !in_array($role,['owner','administrator','editor','support'],true) || !in_array($active,[0,1],true)) {
                $pdo->rollBack(); respond(422,['error'=>'invalid_admin_user']);
            }
            if ($id===(int)$admin['id'] && ($active===0 || $role!=='owner')) {
                $pdo->rollBack(); respond(422,['error'=>'cannot_change_own_owner_access']);
            }
            if ($target['role']==='owner' && ($role!=='owner' || $active===0)) {
                $owners=$pdo->query("SELECT id FROM admin_users WHERE role='owner' AND is_active=1 FOR UPDATE")->fetchAll();
                if (count($owners)<=1) { $pdo->rollBack(); respond(409,['error'=>'last_active_owner_required']); }
            }
            if ($password!=='') {
                $q=$pdo->prepare('UPDATE admin_users SET name=?,role=?,is_active=?,password_hash=? WHERE id=?');
                $q->execute([$name,$role,$active,password_hash($password,PASSWORD_ARGON2ID),$id]);
            } else {
                $q=$pdo->prepare('UPDATE admin_users SET name=?,role=?,is_active=? WHERE id=?');
                $q->execute([$name,$role,$active,$id]);
            }
            audit($pdo,$admin,'admin_user.update','admin_user',(string)$id);
            $pdo->commit();
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $error;
        }
        respond(200,['ok'=>true]);
    }

    if ($path === '/admin/audit-log' && $method === 'GET') {
        requireRole($admin, ['owner']);
        $limit = max(1, min(200, (int)($_GET['limit'] ?? 100)));
        $q = $pdo->prepare('SELECT l.id,l.action,l.entity_type,l.entity_id,l.created_at,u.name AS admin_name,u.email AS admin_email FROM admin_audit_log l LEFT JOIN admin_users u ON u.id=l.admin_user_id ORDER BY l.created_at DESC,l.id DESC LIMIT ?');
        $q->bindValue(1, $limit, PDO::PARAM_INT);
        $q->execute();
        respond(200, ['entries'=>$q->fetchAll()]);
    }

    if ($path === '/admin/bootstrap' && $method === 'GET') {
        // Support staff handle the inbox only; do not disclose CMS, media or site settings.
        $messages = $pdo->query('SELECT id,name,email,subject,message,status,created_at,updated_at FROM contact_messages ORDER BY created_at DESC LIMIT 100')->fetchAll();
        if ($admin['role'] === 'support') {
            respond(200, ['user'=>$admin,'pages'=>[],'universes'=>[],'messages'=>$messages,'settings'=>[],'media'=>[]]);
        }
        $pages = $pdo->query('SELECT slug,title,content_json,status,updated_at FROM content_pages ORDER BY slug')->fetchAll();
        foreach ($pages as &$page) $page['content'] = jsonColumn($page, 'content_json');
        unset($page);
        $brands = $pdo->query('SELECT slug,name,eyebrow,description,detail,category,website_url,status,sort_order,logo_url,logo_background,cover_image_url,contact_email,contact_phone,whatsapp_url,instagram_url,facebook_url,youtube_url,linkedin_url,contact_address,updated_at FROM brand_universes ORDER BY sort_order,id')->fetchAll();
        $settings = $pdo->query('SELECT setting_key,setting_value FROM site_settings ORDER BY setting_key')->fetchAll();
        $media = $pdo->query('SELECT id,original_name,storage_name,mime_type,file_size,alt_text,created_at FROM media_assets ORDER BY created_at DESC LIMIT 200')->fetchAll();
        foreach ($settings as &$setting) $setting['value'] = json_decode((string)$setting['setting_value'], true);
        unset($setting);
        respond(200, ['user'=>$admin,'pages'=>$pages,'universes'=>$brands,'messages'=>$messages,'settings'=>$settings,'media'=>$media]);
    }

    if (preg_match('#^/admin/pages/(?!home$)([a-z0-9-]{2,100})$#', $path, $match) && $method === 'PUT') {
        requireCsrf(); requireRole($admin, ['owner','administrator','editor']);
        $slug = $match[1];
        if (in_array($slug, ['api','painel','assets','storage','server'], true)) respond(422, ['error'=>'reserved_page_slug']);
        $input = body();
        $title = trim((string)($input['title'] ?? ''));
        $summary = trim((string)($input['summary'] ?? ''));
        $content = trim((string)($input['content'] ?? ''));
        if ($title === '' || mb_strlen($title)>180 || mb_strlen($summary)>500 || mb_strlen($content)>12000) {
            respond(422, ['error'=>'invalid_page_content']);
        }
        $payload = ['summary'=>$summary,'content'=>$content];
        try {
            $pdo->beginTransaction();
            $q = $pdo->prepare("INSERT INTO content_pages (slug,title,content_json,status,updated_by) VALUES (?,?,?,'draft',?) ON DUPLICATE KEY UPDATE title=VALUES(title),content_json=VALUES(content_json),status='draft',updated_by=VALUES(updated_by)");
            $q->execute([$slug,$title,json_encode($payload,JSON_UNESCAPED_UNICODE),(int)$admin['id']]);
            audit($pdo,$admin,'page.save_draft','page',$slug);
            $pdo->commit();
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $error;
        }
        respond(200,['ok'=>true,'slug'=>$slug,'status'=>'draft']);
    }

    if (preg_match('#^/admin/pages/(?!home/)([a-z0-9-]{2,100})/publish$#', $path, $match) && $method === 'POST') {
        requireCsrf(); requireRole($admin, ['owner','administrator','editor']);
        $slug = $match[1];
        if (in_array($slug, ['api','painel','assets','storage','server'], true)) respond(422, ['error'=>'reserved_page_slug']);
        try {
            $pdo->beginTransaction();
            $exists = $pdo->prepare('SELECT id FROM content_pages WHERE slug=? LIMIT 1 FOR UPDATE');
            $exists->execute([$slug]);
            if (!$exists->fetch()) { $pdo->rollBack(); respond(404,['error'=>'page_not_found']); }
            $q = $pdo->prepare("UPDATE content_pages SET status='published',updated_by=? WHERE slug=?");
            $q->execute([(int)$admin['id'],$slug]);
            audit($pdo,$admin,'page.publish','page',$slug);
            $pdo->commit();
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $error;
        }
        respond(200,['ok'=>true,'status'=>'published']);
    }

    if ($path === '/admin/pages/home' && $method === 'PATCH') {
        requireCsrf(); requireRole($admin, ['owner','administrator','editor']);
        $input = body();
        $allowed = ['headline','subheadline','intro','groupHeadline','groupLead','groupBody','processHeadline','processIntro','universeEyebrow','universeHeadline','universeAccent','universeIntro','essenceHeadline','essenceAccent','essenceIntro','closingHeadline','closingAccent','closingIntro'];
        $content = [];
        foreach ($allowed as $key) {
            if (!isset($input[$key]) || !is_string($input[$key]) || mb_strlen($input[$key]) > 3000) respond(422, ['error'=>'invalid_page_content','field'=>$key]);
            $content[$key] = trim($input[$key]);
        }
        if (!isset($input['processSteps']) || !is_array($input['processSteps']) || count($input['processSteps']) !== 4) respond(422, ['error'=>'invalid_process_steps']);
        $content['processSteps'] = [];
        foreach ($input['processSteps'] as $index => $step) {
            if (!is_array($step) || !is_string($step['title'] ?? null) || !is_string($step['text'] ?? null) || mb_strlen($step['title']) > 80 || mb_strlen($step['text']) > 500) respond(422, ['error'=>'invalid_process_step','index'=>$index]);
            $content['processSteps'][] = ['title'=>trim($step['title']),'text'=>trim($step['text'])];
        }
        try {
            $pdo->beginTransaction();
            $q = $pdo->prepare("INSERT INTO content_pages (slug,title,content_json,status,updated_by) VALUES ('home','Página inicial',?,'draft',?) ON DUPLICATE KEY UPDATE content_json=VALUES(content_json),status='draft',updated_by=VALUES(updated_by)");
            $q->execute([json_encode($content, JSON_UNESCAPED_UNICODE), (int)$admin['id']]);
            audit($pdo, $admin, 'page.save_draft', 'page', 'home');
            $pdo->commit();
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $error;
        }
        respond(200, ['ok'=>true,'status'=>'draft']);
    }

    if ($path === '/admin/pages/home/publish' && $method === 'POST') {
        requireCsrf(); requireRole($admin, ['owner','administrator','editor']);
        try {
            $pdo->beginTransaction();
            $exists = $pdo->query("SELECT id FROM content_pages WHERE slug='home' LIMIT 1 FOR UPDATE");
            if (!$exists->fetch()) { $pdo->rollBack(); respond(404, ['error'=>'page_not_found']); }
            $q = $pdo->prepare("UPDATE content_pages SET status='published',updated_by=? WHERE slug='home'");
            $q->execute([(int)$admin['id']]);
            audit($pdo, $admin, 'page.publish', 'page', 'home');
            $pdo->commit();
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $error;
        }
        respond(200, ['ok'=>true,'status'=>'published']);
    }

    if (preg_match('#^/admin/universes/([a-z0-9-]{2,100})$#', $path, $match) && $method === 'PUT') {
        requireCsrf(); requireRole($admin, ['owner','administrator','editor']);
        $input = body(); $slug = $match[1];
        $name = trim((string)($input['name'] ?? ''));
        $category = trim((string)($input['category'] ?? ''));
        $status = (string)($input['status'] ?? 'in-development');
        $url = trim((string)($input['website_url'] ?? ''));
        if ($name === '' || mb_strlen($name)>180 || mb_strlen($category)>80 || !in_array($status,['active','in-development','archived'],true)) respond(422, ['error'=>'invalid_universe']);
        if ($url !== '' && (!filter_var($url,FILTER_VALIDATE_URL) || !str_starts_with($url,'https://'))) respond(422, ['error'=>'invalid_https_url']);
        $profile = [];
        foreach (['logo_url','cover_image_url','contact_email','contact_phone','whatsapp_url','whatsapp_message','instagram_url','facebook_url','youtube_url','linkedin_url','contact_address'] as $field) {
            $value = trim((string)($input[$field] ?? ''));
            $maxLength = $field === 'contact_email' ? 190 : ($field === 'contact_phone' ? 80 : 500);
            if (mb_strlen($value) > $maxLength) respond(422, ['error'=>'invalid_universe_field','field'=>$field]);
            if (in_array($field,['logo_url','cover_image_url'],true) && $value !== '' &&
                !str_starts_with($value,'/api/media/') && (!filter_var($value,FILTER_VALIDATE_URL) || !str_starts_with($value,'https://'))) {
                respond(422,['error'=>'invalid_image_url','field'=>$field]);
            }
            if (in_array($field,['whatsapp_url','instagram_url','facebook_url','youtube_url','linkedin_url'],true) && $value !== '' && (!filter_var($value,FILTER_VALIDATE_URL) || !str_starts_with($value,'https://'))) respond(422,['error'=>'invalid_https_url','field'=>$field]);
            if ($field === 'contact_email' && $value !== '' && !filter_var($value,FILTER_VALIDATE_EMAIL)) respond(422,['error'=>'invalid_email','field'=>$field]);
            $profile[$field] = $value !== '' ? $value : null;
        }
        $logoBackground=(string)($input['logo_background']??'transparent');
        if (!in_array($logoBackground,['transparent','black','white','brand'],true)) respond(422,['error'=>'invalid_logo_background']);
        $values = [$name,trim((string)($input['eyebrow']??'')),trim((string)($input['description']??'')),trim((string)($input['detail']??'')),$category,$url ?: null,$status,(int)($input['sort_order']??0),$profile['logo_url'],$logoBackground,$profile['cover_image_url'],$profile['contact_email'],$profile['contact_phone'],$profile['whatsapp_url'],$profile['instagram_url'],$profile['facebook_url'],$profile['youtube_url'],$profile['linkedin_url'],$profile['contact_address'],(int)$admin['id'],$slug];
        $columns = 'name,eyebrow,description,detail,category,website_url,status,sort_order,logo_url,logo_background,cover_image_url,contact_email,contact_phone,whatsapp_url,instagram_url,facebook_url,youtube_url,linkedin_url,contact_address,updated_by,slug';
        $updates = 'name=VALUES(name),eyebrow=VALUES(eyebrow),description=VALUES(description),detail=VALUES(detail),category=VALUES(category),website_url=VALUES(website_url),status=VALUES(status),sort_order=VALUES(sort_order),logo_url=VALUES(logo_url),logo_background=VALUES(logo_background),cover_image_url=VALUES(cover_image_url),contact_email=VALUES(contact_email),contact_phone=VALUES(contact_phone),whatsapp_url=VALUES(whatsapp_url),instagram_url=VALUES(instagram_url),facebook_url=VALUES(facebook_url),youtube_url=VALUES(youtube_url),linkedin_url=VALUES(linkedin_url),contact_address=VALUES(contact_address),updated_by=VALUES(updated_by)';
        $q = $pdo->prepare("INSERT INTO brand_universes ($columns) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE $updates");
        $q->execute($values);
        audit($pdo, $admin, 'universe.save', 'universe', $slug);
        respond(200, ['ok'=>true,'slug'=>$slug]);
    }

    if (preg_match('#^/admin/messages/(\d+)$#', $path, $match) && $method === 'PATCH') {
        requireCsrf(); requireRole($admin, ['owner','administrator','editor','support']);
        $input=body(); $status=(string)($input['status']??'');
        $map=['Novo'=>'new','Em atendimento'=>'in_progress','Concluído'=>'done','Arquivado'=>'archived'];
        if (!isset($map[$status])) respond(422,['error'=>'invalid_message_status']);
        $exists=$pdo->prepare('SELECT id FROM contact_messages WHERE id=? LIMIT 1');
        $exists->execute([(int)$match[1]]);
        if (!$exists->fetch()) respond(404,['error'=>'message_not_found']);
        $q=$pdo->prepare('UPDATE contact_messages SET status=? WHERE id=?');
        $q->execute([$map[$status],(int)$match[1]]);
        audit($pdo,$admin,'message.status','contact_message',$match[1]);
        respond(200,['ok'=>true]);
    }

    if ($path === '/admin/media' && $method === 'POST') {
        requireCsrf(); requireRole($admin, ['owner','administrator','editor']);
        if (empty($_FILES['file']) || !is_array($_FILES['file'])) respond(422, ['error'=>'file_required']);
        $upload = $_FILES['file'];
        if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($upload['tmp_name'] ?? '')) respond(422, ['error'=>'upload_failed']);
        $size = (int)($upload['size'] ?? 0);
        if ($size < 1 || $size > 10 * 1024 * 1024) respond(413, ['error'=>'file_too_large']);
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($upload['tmp_name']);
        $extensions = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
        if (!isset($extensions[$mime])) respond(415, ['error'=>'unsupported_file_type']);
        $imageInfo = @getimagesize($upload['tmp_name']);
        if (!$imageInfo || ($imageInfo['mime'] ?? '') !== $mime ||
            (int)$imageInfo[0] < 1 || (int)$imageInfo[1] < 1 ||
            (int)$imageInfo[0] * (int)$imageInfo[1] > 40000000) {
            respond(415, ['error'=>'invalid_image']);
        }
        $storageName = bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
        $directory = dirname(__DIR__) . '/storage/media';
        if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) respond(500, ['error'=>'storage_unavailable']);
        $destination = $directory . '/' . $storageName;
        if (!move_uploaded_file($upload['tmp_name'], $destination)) respond(500, ['error'=>'storage_write_failed']);
        $original = mb_substr(basename((string)($upload['name'] ?? 'imagem')), 0, 255);
        $alt = trim((string)($_POST['alt_text'] ?? ''));
        try {
            $pdo->beginTransaction();
            $q = $pdo->prepare('INSERT INTO media_assets (original_name,storage_name,mime_type,file_size,alt_text,uploaded_by) VALUES (?,?,?,?,?,?)');
            $q->execute([$original,$storageName,$mime,$size,mb_substr($alt,0,255),(int)$admin['id']]);
            $id = (int)$pdo->lastInsertId();
            audit($pdo,$admin,'media.upload','media_asset',(string)$id);
            $pdo->commit();
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if (is_file($destination)) unlink($destination);
            throw $error;
        }
        respond(201,['ok'=>true,'asset'=>['id'=>$id,'original_name'=>$original,'storage_name'=>$storageName,'mime_type'=>$mime,'file_size'=>$size,'alt_text'=>mb_substr($alt,0,255),'url'=>'/api/media/'.$storageName]]);
    }

    if (preg_match('#^/admin/media/(\\d+)$#', $path, $match) && $method === 'DELETE') {
        requireCsrf(); requireRole($admin, ['owner','administrator']);
        $q=$pdo->prepare('SELECT storage_name FROM media_assets WHERE id=? LIMIT 1');
        $q->execute([(int)$match[1]]);
        $asset=$q->fetch();
        if (!$asset) respond(404,['error'=>'media_not_found']);
        $directory=dirname(__DIR__).'/storage/media';
        $file=$directory.'/'.$asset['storage_name'];
        $tombstone=$directory.'/'.bin2hex(random_bytes(16)).'.delete';
        $moved=false;
        if (is_file($file)) {
            if (!rename($file,$tombstone)) respond(500,['error'=>'media_storage_unavailable']);
            $moved=true;
        }
        try {
            $pdo->beginTransaction();
            $pdo->prepare('DELETE FROM media_assets WHERE id=?')->execute([(int)$match[1]]);
            audit($pdo,$admin,'media.delete','media_asset',$match[1]);
            $pdo->commit();
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if ($moved && is_file($tombstone)) rename($tombstone,$file);
            throw $error;
        }
        if ($moved && is_file($tombstone) && !unlink($tombstone)) {
            error_log('[FIXXAR API] mídia removida do catálogo; arquivo temporário pendente de limpeza.');
        }
        respond(200,['ok'=>true]);
    }

    if ($path === '/admin/settings' && $method === 'PATCH') {
        requireCsrf(); requireRole($admin, ['owner','administrator']);
        $input=body();
        $allowedSettings = ['site_name','contact_email','contact_phone','whatsapp_url','whatsapp_message','instagram_url','facebook_url','youtube_url','linkedin_url','contact_address','footer_text','seo_title','seo_description','body_text_size','maintenance'];
        foreach ($input as $key=>$value) {
            if (!in_array($key,$allowedSettings,true)) respond(422,['error'=>'invalid_setting_key']);
            if ($key === 'maintenance' && !is_bool($value)) respond(422,['error'=>'invalid_setting_value','field'=>$key]);
            if ($key !== 'maintenance' && (!is_string($value) || mb_strlen($value) > (in_array($key,['seo_description','contact_address','footer_text'],true) ? 500 : 500))) {
                respond(422,['error'=>'invalid_setting_value','field'=>$key]);
            }
            if ($key === 'contact_email' && $value !== '' && !filter_var($value,FILTER_VALIDATE_EMAIL)) respond(422,['error'=>'invalid_setting_value','field'=>$key]);
            if (in_array($key,['whatsapp_url','instagram_url','facebook_url','youtube_url','linkedin_url'],true) && $value !== '' && (!filter_var($value,FILTER_VALIDATE_URL) || !str_starts_with($value,'https://'))) respond(422,['error'=>'invalid_https_url','field'=>$key]);
            if ($key === 'site_name' && trim($value) === '') respond(422,['error'=>'invalid_setting_value','field'=>$key]);
            $q=$pdo->prepare('INSERT INTO site_settings (setting_key,setting_value,updated_by) VALUES (?,?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),updated_by=VALUES(updated_by)');
            $q->execute([$key,json_encode($value,JSON_UNESCAPED_UNICODE),(int)$admin['id']]);
        }
        audit($pdo,$admin,'settings.update','site_settings');
        respond(200,['ok'=>true]);
    }

    respond(404, ['error'=>'route_not_found']);
} catch (Throwable $error) {
    error_log('[FIXXAR API] ' . $error->getMessage());
    respond(500, ['error'=>'internal_server_error']);
}
