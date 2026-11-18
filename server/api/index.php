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
        $universes = $pdo->query("SELECT slug,name,eyebrow,description,detail,category,website_url,status,sort_order FROM brand_universes WHERE status IN ('active','in-development') ORDER BY sort_order,id")->fetchAll();
        $publishedPages = $pdo->query("SELECT slug,title FROM content_pages WHERE status='published' AND slug<>'home' ORDER BY title")->fetchAll();
        $settingQuery = $pdo->prepare("SELECT setting_key,setting_value FROM site_settings WHERE setting_key IN ('seo_title','seo_description','maintenance')");
        $settingQuery->execute();
        $seo = ['title'=>'FIXXAR GROUP — Ideias que ganham forma','description'=>'Criatividade, comunicação, conhecimento e inovação em um ecossistema de marcas.'];
        $maintenance = false;
        foreach ($settingQuery->fetchAll() as $setting) {
            $value = json_decode((string)$setting['setting_value'], true);
            if ($setting['setting_key'] === 'maintenance') { $maintenance = $value === true || $value === 1 || $value === '1'; continue; }
            if (is_string($value) && trim($value) !== '') $seo[$setting['setting_key'] === 'seo_title' ? 'title' : 'description'] = trim($value);
        }
        respond(200, ['page'=>$page ?: null,'pages'=>$publishedPages,'universes'=>$universes,'seo'=>$seo,'maintenance'=>$maintenance]);
    }

    $admin = requireAdmin($pdo);

    if ($path === '/admin/users' && $method === 'GET') {
        requireRole($admin, ['owner','administrator']);
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
        $targetQuery=$pdo->prepare('SELECT id,name,email,role,is_active FROM admin_users WHERE id=? LIMIT 1');
        $targetQuery->execute([$id]);
        $target=$targetQuery->fetch();
        if (!$target) respond(404,['error'=>'admin_user_not_found']);
        $name=trim((string)($input['name']??$target['name']));
        $role=(string)($input['role']??$target['role']);
        $active=array_key_exists('is_active',$input)?(int)(bool)$input['is_active']:(int)$target['is_active'];
        $password=(string)($input['password']??'');
        if ($name==='' || mb_strlen($name)>120 || !in_array($role,['owner','administrator','editor','support'],true) || !in_array($active,[0,1],true) ||
            ($password!=='' && (strlen($password)<14 || strlen($password)>4096))) respond(422,['error'=>'invalid_admin_user']);
        if ($id===(int)$admin['id'] && ($active===0 || $role!=='owner')) respond(422,['error'=>'cannot_change_own_owner_access']);
        if ($target['role']==='owner' && ($role!=='owner' || $active===0)) {
            $owners=(int)$pdo->query("SELECT COUNT(*) FROM admin_users WHERE role='owner' AND is_active=1")->fetchColumn();
            if ($owners<=1) respond(409,['error'=>'last_active_owner_required']);
        }
        if ($password!=='') {
            $q=$pdo->prepare('UPDATE admin_users SET name=?,role=?,is_active=?,password_hash=? WHERE id=?');
            $q->execute([$name,$role,$active,password_hash($password,PASSWORD_ARGON2ID),$id]);
        } else {
            $q=$pdo->prepare('UPDATE admin_users SET name=?,role=?,is_active=? WHERE id=?');
            $q->execute([$name,$role,$active,$id]);
        }
        audit($pdo,$admin,'admin_user.update','admin_user',(string)$id);
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
        $pages = $pdo->query('SELECT slug,title,content_json,status,updated_at FROM content_pages ORDER BY slug')->fetchAll();
        foreach ($pages as &$page) $page['content'] = jsonColumn($page, 'content_json');
        unset($page);
        $brands = $pdo->query('SELECT slug,name,eyebrow,description,detail,category,website_url,status,sort_order,updated_at FROM brand_universes ORDER BY sort_order,id')->fetchAll();
        $messages = $pdo->query('SELECT id,name,email,subject,message,status,created_at,updated_at FROM contact_messages ORDER BY created_at DESC LIMIT 100')->fetchAll();
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
        $q = $pdo->prepare("INSERT INTO content_pages (slug,title,content_json,status,updated_by) VALUES (?,?,?,'draft',?) ON DUPLICATE KEY UPDATE title=VALUES(title),content_json=VALUES(content_json),status='draft',updated_by=VALUES(updated_by)");
        $q->execute([$slug,$title,json_encode($payload,JSON_UNESCAPED_UNICODE),(int)$admin['id']]);
        audit($pdo,$admin,'page.save_draft','page',$slug);
        respond(200,['ok'=>true,'slug'=>$slug,'status'=>'draft']);
    }

    if (preg_match('#^/admin/pages/(?!home/)([a-z0-9-]{2,100})/publish$#', $path, $match) && $method === 'POST') {
        requireCsrf(); requireRole($admin, ['owner','administrator','editor']);
        $slug = $match[1];
        if (in_array($slug, ['api','painel','assets','storage','server'], true)) respond(422, ['error'=>'reserved_page_slug']);
        $q = $pdo->prepare("UPDATE content_pages SET status='published',updated_by=? WHERE slug=?");
        $q->execute([(int)$admin['id'],$slug]);
        if (!$q->rowCount()) respond(404,['error'=>'page_not_found']);
        audit($pdo,$admin,'page.publish','page',$slug);
        respond(200,['ok'=>true,'status'=>'published']);
    }

    if ($path === '/admin/pages/home' && $method === 'PATCH') {
        requireCsrf(); requireRole($admin, ['owner','administrator','editor']);
        $input = body();
        $allowed = ['headline','subheadline','intro','groupHeadline','groupLead','groupBody','processHeadline','processIntro'];
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
        $q = $pdo->prepare("INSERT INTO content_pages (slug,title,content_json,status,updated_by) VALUES ('home','Página inicial',?,'draft',?) ON DUPLICATE KEY UPDATE content_json=VALUES(content_json),status='draft',updated_by=VALUES(updated_by)");
        $q->execute([json_encode($content, JSON_UNESCAPED_UNICODE), (int)$admin['id']]);
        audit($pdo, $admin, 'page.save_draft', 'page', 'home');
        respond(200, ['ok'=>true,'status'=>'draft']);
    }

    if ($path === '/admin/pages/home/publish' && $method === 'POST') {
        requireCsrf(); requireRole($admin, ['owner','administrator','editor']);
        $q = $pdo->prepare("UPDATE content_pages SET status='published',updated_by=? WHERE slug='home'");
        $q->execute([(int)$admin['id']]);
        if (!$q->rowCount()) respond(404, ['error'=>'page_not_found']);
        audit($pdo, $admin, 'page.publish', 'page', 'home');
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
        $storageName = bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
        $directory = dirname(__DIR__) . '/storage/media';
        if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) respond(500, ['error'=>'storage_unavailable']);
        $destination = $directory . '/' . $storageName;
        if (!move_uploaded_file($upload['tmp_name'], $destination)) respond(500, ['error'=>'storage_write_failed']);
        $original = mb_substr(basename((string)($upload['name'] ?? 'imagem')), 0, 255);
        $alt = trim((string)($_POST['alt_text'] ?? ''));
        $q = $pdo->prepare('INSERT INTO media_assets (original_name,storage_name,mime_type,file_size,alt_text,uploaded_by) VALUES (?,?,?,?,?,?)');
        $q->execute([$original,$storageName,$mime,$size,mb_substr($alt,0,255),(int)$admin['id']]);
        $id = (int)$pdo->lastInsertId();
        audit($pdo,$admin,'media.upload','media_asset',(string)$id);
        respond(201,['ok'=>true,'asset'=>['id'=>$id,'original_name'=>$original,'storage_name'=>$storageName,'mime_type'=>$mime,'file_size'=>$size,'alt_text'=>$alt,'url'=>'/api/media/'.$storageName]]);
    }

    if (preg_match('#^/admin/media/(\\d+)$#', $path, $match) && $method === 'DELETE') {
        requireCsrf(); requireRole($admin, ['owner','administrator']);
        $q=$pdo->prepare('SELECT storage_name FROM media_assets WHERE id=? LIMIT 1');
        $q->execute([(int)$match[1]]);
        $asset=$q->fetch();
        if (!$asset) respond(404,['error'=>'media_not_found']);
        $pdo->prepare('DELETE FROM media_assets WHERE id=?')->execute([(int)$match[1]]);
        $file=dirname(__DIR__).'/storage/media/'.$asset['storage_name'];
        if (is_file($file)) unlink($file);
        audit($pdo,$admin,'media.delete','media_asset',$match[1]);
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
