<?php
declare(strict_types=1);

$isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
session_set_cookie_params([
    'httponly' => true,
    'secure' => $isHttps,
    'samesite' => 'Lax',
]);
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = getenv('INTERNTRACK_DB_HOST') ?: '127.0.0.1';
    $name = getenv('INTERNTRACK_DB_NAME') ?: 'interntrack';
    $user = getenv('INTERNTRACK_DB_USER') ?: 'root';
    $password = getenv('INTERNTRACK_DB_PASSWORD') ?: '';
    $pdo = new PDO(
        "mysql:host={$host};dbname={$name};charset=utf8mb4",
        $user,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
    return $pdo;
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrf_token(): string
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function check_csrf(): void
{
    $token = (string) ($_POST['csrf_token'] ?? '');
    if ($token === '' || !hash_equals(csrf_token(), $token)) {
        http_response_code(400);
        exit('Your session expired. Go back, refresh the page, and try again.');
    }
}

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    $query = db()->prepare('SELECT id, full_name, email, role FROM users WHERE id = ?');
    $query->execute([(int) $_SESSION['user_id']]);
    $user = $query->fetch();
    if (!$user) {
        $_SESSION = [];
        session_regenerate_id(true);
        return null;
    }
    return $user;
}

function require_role(string $role): array
{
    $user = current_user();
    if (!$user) {
        header('Location: auth.php');
        exit;
    }
    if ($user['role'] !== $role) {
        http_response_code(403);
        exit('You do not have permission to access this page.');
    }
    return $user;
}

function flash(string $message, string $kind = 'success'): void
{
    $_SESSION['flash'] = ['message' => $message, 'kind' => $kind];
}

function take_flash(): ?array
{
    $message = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return is_array($message) ? $message : null;
}

function notify(int $userId, string $message, string $link = 'dashboard.php'): void
{
    $query = db()->prepare('INSERT INTO notifications (user_id, message, link) VALUES (?, ?, ?)');
    $query->execute([$userId, $message, $link]);
}

function notify_admins(string $message, string $link = 'admin.php'): void
{
    $query = db()->prepare("SELECT id FROM users WHERE role = 'admin'");
    $query->execute();
    $insert = db()->prepare('INSERT INTO notifications (user_id, message, link) VALUES (?, ?, ?)');
    foreach ($query->fetchAll() as $admin) {
        $insert->execute([(int) $admin['id'], $message, $link]);
    }
}

function redirect(string $location): never
{
    header('Location: ' . $location);
    exit;
}

function post_string(string $key, int $maxLength = 5000): string
{
    $value = trim(request_string($_POST, $key));
    if (strlen($value) > $maxLength) {
        throw new InvalidArgumentException('One of the fields is too long.');
    }
    return $value;
}

function request_string(array $source, string $key): string
{
    $value = $source[$key] ?? '';
    if (!is_string($value)) {
        throw new InvalidArgumentException('Check the form values and try again.');
    }
    return $value;
}

set_exception_handler(static function (Throwable $exception): void {
    error_log('InternTrack uncaught error: ' . $exception);
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=UTF-8');
    }
    echo '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Temporarily unavailable — InternTrack</title><body style="font:16px sans-serif;max-width:620px;margin:12vh auto;padding:24px;color:#24342b"><h1>We could not load that page.</h1><p>Please try again. If the problem continues, check that MySQL is running and the InternTrack database is set up.</p><p><a href="index.php">Return to InternTrack</a></p></body></html>';
});
