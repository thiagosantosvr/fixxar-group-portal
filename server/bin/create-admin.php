<?php
declare(strict_types=1);

/** Run from SSH/CLI only: php server/bin/create-admin.php */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$configPath = dirname(__DIR__) . '/config.php';
if (!is_file($configPath)) { fwrite(STDERR, "Configure server/config.php first.\n"); exit(1); }
$config = require $configPath;
$d = $config['database'];
$dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $d['host'], $d['port'] ?? 3306, $d['name']);
$pdo = new PDO($dsn, $d['user'], $d['password'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$name = trim((string)readline('Administrator name: '));
$email = strtolower(trim((string)readline('Administrator email: ')));
fwrite(STDOUT, 'Temporary password (minimum 14 characters): ');
$stty = PHP_OS_FAMILY !== 'Windows' ? shell_exec('stty -g 2>/dev/null') : null;
if (is_string($stty) && $stty !== '') shell_exec('stty -echo');
$password = rtrim((string)fgets(STDIN), "\\r\\n");
if (is_string($stty) && $stty !== '') { shell_exec('stty ' . escapeshellarg(trim($stty))); fwrite(STDOUT, "\\n"); }
if ($name === '' || !filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($password)<14) {
    fwrite(STDERR, "Invalid name/email or password too short.\n"); exit(1);
}
$count = (int)$pdo->query("SELECT COUNT(*) FROM admin_users WHERE role='owner'")->fetchColumn();
if ($count > 0) { fwrite(STDERR, "An owner already exists. Use the account-management process.\n"); exit(1); }
$hash = password_hash($password, PASSWORD_ARGON2ID);
$q = $pdo->prepare("INSERT INTO admin_users (name,email,password_hash,role) VALUES (?,?,?,'owner')");
$q->execute([$name,$email,$hash]);
fwrite(STDOUT, "Owner created. Store credentials securely and remove shell history if applicable.\n");
