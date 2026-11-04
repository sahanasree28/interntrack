<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';

$modeInput = $_GET['mode'] ?? 'login';
$mode = is_string($modeInput) ? $modeInput : 'login';
if (!in_array($mode, ['login', 'register', 'admin'], true)) {
    $mode = 'login';
}
$error = '';
$name = '';
$email = '';
$databaseReady = true;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $modeInput = $_POST['mode'] ?? 'login';
    $mode = is_string($modeInput) ? $modeInput : 'login';
    if (!in_array($mode, ['login', 'register', 'admin'], true)) {
        $mode = 'login';
    }
    try {
        check_csrf();
        $name = trim(request_string($_POST, 'full_name'));
        $email = strtolower(trim(request_string($_POST, 'email')));
        $password = request_string($_POST, 'password');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) {
            $error = 'Enter a valid email address.';
        } elseif ($mode === 'register') {
            $confirm = request_string($_POST, 'confirm_password');
            if ($name === '' || strlen($name) > 120) {
                $error = 'Enter your full name (up to 120 characters).';
            } elseif (strlen($password) < 8 || strlen($password) > 4096) {
                $error = 'Choose a password with at least 8 characters.';
            } elseif ($password !== $confirm) {
                $error = 'The passwords do not match.';
            } else {
                $insert = db()->prepare('INSERT INTO users (full_name, email, password_hash) VALUES (?, ?, ?)');
                $insert->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
                session_regenerate_id(true);
                $_SESSION['user_id'] = (int) db()->lastInsertId();
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                redirect('dashboard.php');
            }
        } elseif ($password === '') {
            $error = 'Enter your password.';
        } else {
            $query = db()->prepare('SELECT id, full_name, password_hash, role FROM users WHERE email = ? LIMIT 1');
            $query->execute([$email]);
            $account = $query->fetch();
            $expectedRole = $mode === 'admin' ? 'admin' : null;
            if (!$account || !password_verify($password, $account['password_hash']) ||
                ($expectedRole !== null && $account['role'] !== $expectedRole)) {
                $error = 'Email or password is incorrect.';
            } else {
                session_regenerate_id(true);
                $_SESSION['user_id'] = (int) $account['id'];
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                redirect($account['role'] === 'admin' ? 'admin.php' : 'dashboard.php');
            }
        }
    } catch (PDOException $exception) {
        if ($exception->getCode() === '23000' && $mode === 'register') {
            $error = 'An account with that email already exists. Try logging in.';
        } else {
            error_log('InternTrack authentication database error: ' . $exception->getMessage());
            $databaseReady = false;
        }
    } catch (InvalidArgumentException $exception) {
        $error = $exception->getMessage();
    }
}

$isRegister = $mode === 'register';
$isAdminLogin = $mode === 'admin';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= $isRegister ? 'Create your account' : ($isAdminLogin ? 'Administrator login' : 'Log in') ?> — InternTrack</title>
  <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/style.css">
</head>
<body class="auth-page">
  <header class="site-header auth-header"><a class="brand" href="index.php"><span class="brand-mark">i</span> InternTrack</a><a class="text-link" href="index.php">← Back to home</a></header>
  <main class="auth-layout">
    <section class="auth-aside">
      <div class="eyebrow"><span class="eyebrow-dot"></span> A clearer path to your future</div>
      <h1><?= $isRegister ? 'Your next big thing starts with one step.' : 'Welcome back to your journey.' ?></h1>
      <p>Opportunities, tasks, and feedback — all moving forward with you.</p>
      <div class="auth-aside-card"><span class="aside-spark">✳</span><strong>Progress is built<br>one task at a time.</strong><span>Keep showing up. You’re closer than you think.</span></div>
    </section>
    <section class="auth-card" aria-labelledby="auth-title">
      <div class="eyebrow"><?= $isAdminLogin ? 'Workspace access' : ($isRegister ? 'Create your student account' : 'Your workspace awaits') ?></div>
      <h2 id="auth-title"><?= $isAdminLogin ? 'Administrator login' : ($isRegister ? 'Get started.' : 'Let’s pick up where you left off.') ?></h2>
      <p class="auth-intro"><?= $isRegister ? 'Create an account to find and manage your internship.' : ($isAdminLogin ? 'Sign in with your administrator account.' : 'Log in to continue building your future.') ?></p>
      <?php if (!$databaseReady): ?><p class="form-message form-error" role="alert">Could not connect to MySQL. Start MySQL in XAMPP and import schema.sql.</p>
      <?php elseif ($error !== ''): ?><p class="form-message form-error" role="alert"><?= e($error) ?></p><?php endif; ?>
      <?php if ($databaseReady): ?>
      <form class="stack-form" method="post" action="auth.php">
        <input type="hidden" name="mode" value="<?= e($mode) ?>"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <?php if ($isRegister): ?><label for="full_name">Full name</label><input id="full_name" name="full_name" type="text" maxlength="120" autocomplete="name" value="<?= e($name) ?>" required><?php endif; ?>
        <label for="email">Email address</label><input id="email" name="email" type="email" maxlength="254" autocomplete="email" value="<?= e($email) ?>" required>
        <label for="password">Password</label><input id="password" name="password" type="password" autocomplete="<?= $isRegister ? 'new-password' : 'current-password' ?>" required>
        <?php if ($isRegister): ?><label for="confirm_password">Confirm password</label><input id="confirm_password" name="confirm_password" type="password" autocomplete="new-password" required><?php endif; ?>
        <button class="button auth-submit" type="submit"><?= $isRegister ? 'Create student account' : 'Log in' ?><span aria-hidden="true">↗</span></button>
      </form>
      <?php endif; ?>
      <p class="auth-switch"><?php if ($isRegister): ?>Already have an account? <a href="auth.php">Log in</a><?php elseif ($isAdminLogin): ?>Student? <a href="auth.php">Student login</a><?php else: ?>New here? <a href="auth.php?mode=register">Create a student account</a><?php endif; ?></p>
      <?php if (!$isAdminLogin && !$isRegister): ?><p class="auth-switch"><a href="auth.php?mode=admin">Administrator login</a></p><?php endif; ?>
    </section>
  </main>
</body>
</html>
