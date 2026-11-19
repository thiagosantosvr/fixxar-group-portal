<?php
declare(strict_types=1);

/**
 * One-time browser setup for the first FIXXAR GROUP owner.
 * Remove this file from the server after creating the owner account.
 */
$configPath = __DIR__ . '/config.php';
if (!is_file($configPath)) {
    http_response_code(503);
    exit('A configuração do FIXXAR GROUP ainda não foi concluída.');
}
$config = require $configPath;
$setupToken = (string)($config['setup_token'] ?? '');
if (strlen($setupToken) < 32 || str_starts_with($setupToken, 'REPLACE_')) {
    http_response_code(503);
    exit('Configure primeiro um token de instalação forte em server/config.php.');
}
$storage = __DIR__ . '/storage';
if (!is_dir($storage) && !mkdir($storage, 0750, true) && !is_dir($storage)) {
    http_response_code(500);
    exit('Não foi possível preparar o armazenamento.');
}
$storageRules = $storage . '/.htaccess';
if (!is_file($storageRules)) {
    @file_put_contents($storageRules, "Require all denied\\n");
}
$lockFile = $storage . '/owner-setup.lock';
$pdo = null;
$errorMessage = '';
$success = false;

try {
    $d = $config['database'];
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $d['host'], (int)($d['port'] ?? 3306), $d['name'], $d['charset'] ?? 'utf8mb4');
    $pdo = new PDO($dsn, $d['user'], $d['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $owners = (int)$pdo->query("SELECT COUNT(*) FROM admin_users WHERE role='owner'")->fetchColumn();
    if ($owners > 0 || is_file($lockFile)) {
        http_response_code(410);
        exit('A configuração inicial já foi concluída. Por segurança, exclua server/instalar-admin.php.');
    }
} catch (Throwable $e) {
    error_log('[FIXXAR setup] ' . $e->getMessage());
    http_response_code(503);
    exit('Não foi possível conectar ao banco. Confira a configuração do servidor.');
}

// Use the PHP session storage configured by the hosting provider.
// DreamHost may not allow PHP to create custom session directories under the site.
if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_name('fixxar_setup');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    if (!@session_start()) {
        error_log('[FIXXAR setup] PHP session_start failed; save_path=' . (string)ini_get('session.save_path'));
        http_response_code(500);
        exit('O servidor não conseguiu iniciar uma sessão PHP. Verifique a configuração de sessões PHP da hospedagem.');
    }
}
if (!isset($_SESSION['setup_csrf'])) $_SESSION['setup_csrf'] = bin2hex(random_bytes(32));
if (!isset($_SESSION['setup_attempts'])) $_SESSION['setup_attempts'] = 0;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if ((int)$_SESSION['setup_attempts'] >= 5) {
        http_response_code(429);
        $errorMessage = 'Muitas tentativas. Feche esta página e tente novamente mais tarde.';
    } else {
        $csrf = (string)($_POST['csrf'] ?? '');
        if (!hash_equals((string)$_SESSION['setup_csrf'], $csrf)) {
            http_response_code(419);
            $errorMessage = 'Sessão expirada. Atualize a página e tente novamente.';
        } else {
            $_SESSION['setup_attempts']++;
            $token = (string)($_POST['setup_token'] ?? '');
            $name = trim((string)($_POST['name'] ?? ''));
            $email = strtolower(trim((string)($_POST['email'] ?? '')));
            $password = (string)($_POST['password'] ?? '');
            $confirm = (string)($_POST['password_confirm'] ?? '');
            if (!hash_equals($setupToken, $token)) {
                $errorMessage = 'Token de instalação incorreto.';
            } elseif ($name === '' || mb_strlen($name) > 120 || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) {
                $errorMessage = 'Confira o nome e o e-mail informados.';
            } elseif (strlen($password) < 14 || strlen($password) > 4096 || $password !== $confirm) {
                $errorMessage = 'A senha deve ter pelo menos 14 caracteres e as duas senhas devem coincidir.';
            } else {
                try {
                    $pdo->beginTransaction();
                    $owners = (int)$pdo->query("SELECT COUNT(*) FROM admin_users WHERE role='owner'")->fetchColumn();
                    if ($owners > 0 || is_file($lockFile)) {
                        $pdo->rollBack();
                        http_response_code(410);
                        $errorMessage = 'A configuração inicial já foi concluída.';
                    } else {
                        $exists = $pdo->prepare('SELECT id FROM admin_users WHERE email=? LIMIT 1');
                        $exists->execute([$email]);
                        if ($exists->fetch()) {
                            $pdo->rollBack();
                            $errorMessage = 'Este e-mail já está cadastrado. Utilize outro endereço.';
                        } else {
                            $insert = $pdo->prepare("INSERT INTO admin_users (name,email,password_hash,role,is_active) VALUES (?,?,?,'owner',1)");
                            $insert->execute([$name, $email, password_hash($password, PASSWORD_ARGON2ID)]);
                            $lock = @fopen($lockFile, 'x');
                            if ($lock === false) {
                                $pdo->rollBack();
                                $errorMessage = 'Não foi possível finalizar o bloqueio de instalação. Contate o suporte.';
                            } else {
                                fwrite($lock, gmdate('c'));
                                fclose($lock);
                                $pdo->commit();
                                $success = true;
                                unset($_SESSION['setup_token'], $_SESSION['setup_csrf']);
                            }
                        }
                    }
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    error_log('[FIXXAR setup] ' . $e->getMessage());
                    $errorMessage = 'Não foi possível criar a conta. Verifique o log do servidor.';
                }
            }
        }
    }
}
?><!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Configuração inicial — FIXXAR GROUP</title>
<style>
*{box-sizing:border-box}body{margin:0;background:#111;color:#f5f3ef;font:16px/1.5 system-ui,sans-serif;display:grid;min-height:100vh;place-items:center;padding:24px}.card{width:min(100%,520px);background:#191919;border:1px solid #383838;border-radius:16px;padding:28px}h1{font-size:25px;margin:0 0 8px}p{color:#c9c6c0}label{display:block;margin:16px 0 6px;font-weight:600}input{width:100%;padding:12px;border:1px solid #555;border-radius:8px;background:#0f0f0f;color:white;font:inherit}button{margin-top:22px;width:100%;padding:13px;border:0;border-radius:8px;background:#ff751f;color:#111;font-weight:800;font-size:16px;cursor:pointer}.notice{padding:12px;border-radius:8px;background:#30251d;margin:16px 0}.success{background:#183323}.small{font-size:13px;color:#aaa}
</style></head>
<body><main class="card">
<h1>FIXXAR GROUP</h1><p>Configuração protegida da primeira conta proprietária.</p>
<?php if ($success): ?>
<div class="notice success"><strong>Conta proprietária criada com sucesso.</strong><br>Agora exclua imediatamente o arquivo <code>server/instalar-admin.php</code> pelo Gerenciador de Arquivos da hospedagem. Depois, acesse <code>/painel</code> e entre com seu e-mail e senha.</div>
<?php else: ?>
<?php if ($errorMessage !== ''): ?><div class="notice"><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<form method="post" autocomplete="off">
<input type="hidden" name="csrf" value="<?= htmlspecialchars((string)$_SESSION['setup_csrf'], ENT_QUOTES, 'UTF-8') ?>">
<label for="setup_token">Token de instalação</label><input id="setup_token" name="setup_token" type="password" required autocomplete="off">
<label for="name">Nome do responsável</label><input id="name" name="name" maxlength="120" required>
<label for="email">E-mail de acesso</label><input id="email" name="email" type="email" maxlength="190" required autocomplete="username">
<label for="password">Senha (mínimo 14 caracteres)</label><input id="password" name="password" type="password" minlength="14" required autocomplete="new-password">
<label for="password_confirm">Confirme a senha</label><input id="password_confirm" name="password_confirm" type="password" minlength="14" required autocomplete="new-password">
<p class="small">A senha é armazenada com Argon2id. O formulário só funciona enquanto não existir uma conta proprietária.</p>
<button type="submit">Criar conta proprietária</button>
</form>
<?php endif; ?>
</main></body></html>
