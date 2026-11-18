<?php
declare(strict_types=1);

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
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, private');
header('Vary: Origin');

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '' && hash_equals((string)$config['app_origin'], $origin)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');
    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, OPTIONS');
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
session_name((string)($config['session_name'] ?? 'fixxar_admin'));
session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Strict']);
session_start();

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
        if (!empty($_SESSION['login_attempt_at']) && time() - (int)$_SESSION['login_attempt_at'] < 1) {
            respond(429, ['error'=>'please_wait']);
        }
        $_SESSION['login_attempt_at'] = time();
        $input = body();
        $email = filter_var(trim((string)($input['email'] ?? '')), FILTER_VALIDATE_EMAIL);
        $password = (string)($input['password'] ?? '');
        if (!$email || $password === '' || strlen($password) > 4096) respond(422, ['error'=>'invalid_credentials']);
        $q = $pdo->prepare('SELECT id,name,email,password_hash,role FROM admin_users WHERE email=? AND is_active=1 LIMIT 1');
        $q->execute([$email]);
        $user = $q->fetch();
        if (!$user || !password_verify($password, $user['password_hash'])) {
            usleep(350000);
            respond(401, ['error'=>'invalid_credentials']);
        }
        session_regenerate_id(true);
        $_SESSION['admin_id'] = (int)$user['id'];
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['audit_salt'] = bin2hex(random_bytes(32));
        $pdo->prepare('UPDATE admin_users SET last_login_at=NOW() WHERE id=?')->execute([(int)$user['id']]);
        audit($pdo, $user, 'auth.login', 'admin_user', (string)$user['id']);
        unset($user['password_hash']);
        respond(200, ['user'=>$user,'csrfToken'=>$_SESSION['csrf_token']]);
    }

    if ($path === '/auth/session' && $method === 'GET') {
        $admin = currentAdmin($pdo);
        if (!$admin) respond(200, ['authenticated'=>false]);
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

    $admin = requireAdmin($pdo);

    if ($path === '/admin/bootstrap' && $method === 'GET') {
        $pages = $pdo->query('SELECT slug,title,content_json,status,updated_at FROM content_pages ORDER BY slug')->fetchAll();
        foreach ($pages as &$page) $page['content'] = jsonColumn($page, 'content_json');
        unset($page);
        $brands = $pdo->query('SELECT slug,name,eyebrow,description,detail,category,website_url,status,sort_order,updated_at FROM brand_universes ORDER BY sort_order,id')->fetchAll();
        $messages = $pdo->query('SELECT id,name,email,subject,message,status,created_at,updated_at FROM contact_messages ORDER BY created_at DESC LIMIT 100')->fetchAll();
        $settings = $pdo->query('SELECT setting_key,setting_value FROM site_settings ORDER BY setting_key')->fetchAll();
        foreach ($settings as &$setting) $setting['value'] = json_decode((string)$setting['setting_value'], true);
        unset($setting);
        respond(200, ['user'=>$admin,'pages'=>$pages,'universes'=>$brands,'messages'=>$messages,'settings'=>$settings]);
    }

    if ($path === '/admin/pages/home' && $method === 'PATCH') {
        requireCsrf(); requireRole($admin, ['owner','administrator','editor']);
        $input = body();
        $allowed = ['headline','subheadline','intro'];
        $content = [];
        foreach ($allowed as $key) {
            if (!isset($input[$key]) || !is_string($input[$key]) || mb_strlen($input[$key]) > 3000) respond(422, ['error'=>'invalid_page_content','field'=>$key]);
            $content[$key] = trim($input[$key]);
        }
        $q = $pdo->prepare("INSERT INTO content_pages (slug,title,content_json,status,updated_by) VALUES ('home','Página inicial',?,'draft',?) ON DUPLICATE KEY UPDATE content_json=VALUES(content_json),status='draft',updated_by=VALUES(updated_by)");
        $q->execute([json_encode($content, JSON_UNESCAPED_UNICODE), (int)$admin['id']]);
        audit($pdo, $admin, 'page.save_draft', 'page', 'home');
        respond(200, ['ok'=>true,'status'=>'draft']);
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
        $values = [$name,trim((string)($input['eyebrow']??'')),trim((string)($input['description']??'')),trim((string)($input['detail']??'')),$category,$url ?: null,$status,(int)($input['sort_order']??0),(int)$admin['id'],$slug];
        $q = $pdo->prepare('INSERT INTO brand_universes (name,eyebrow,description,detail,category,website_url,status,sort_order,updated_by,slug) VALUES (?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE name=VALUES(name),eyebrow=VALUES(eyebrow),description=VALUES(description),detail=VALUES(detail),category=VALUES(category),website_url=VALUES(website_url),status=VALUES(status),sort_order=VALUES(sort_order),updated_by=VALUES(updated_by)');
        $q->execute($values);
        audit($pdo, $admin, 'universe.save', 'universe', $slug);
        respond(200, ['ok'=>true,'slug'=>$slug]);
    }

    if (preg_match('#^/admin/messages/(\d+)$#', $path, $match) && $method === 'PATCH') {
        requireCsrf(); requireRole($admin, ['owner','administrator','editor','support']);
        $input=body(); $status=(string)($input['status']??'');
        $map=['Novo'=>'new','Em atendimento'=>'in_progress','Concluído'=>'done','Arquivado'=>'archived'];
        if (!isset($map[$status])) respond(422,['error'=>'invalid_message_status']);
        $q=$pdo->prepare('UPDATE contact_messages SET status=? WHERE id=?');
        $q->execute([$map[$status],(int)$match[1]]);
        if (!$q->rowCount()) respond(404,['error'=>'message_not_found']);
        audit($pdo,$admin,'message.status','contact_message',$match[1]);
        respond(200,['ok'=>true]);
    }

    if ($path === '/admin/settings' && $method === 'PATCH') {
        requireCsrf(); requireRole($admin, ['owner','administrator']);
        $input=body();
        foreach ($input as $key=>$value) {
            if (!preg_match('/^[a-z][a-z0-9_.-]{1,119}$/',$key)) respond(422,['error'=>'invalid_setting_key']);
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
