<?php
require __DIR__ . '/includes/bootstrap.php';

if (currentUser()) {
    redirect('index.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $statement = db()->prepare('SELECT * FROM users WHERE email = :email');
    $statement->execute([':email' => $email]);
    $user = $statement->fetch();

    if ($user && password_verify($password, $user['password'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $redirectTo = $_SESSION['redirect_after_login'] ?? 'index.php';
        unset($_SESSION['redirect_after_login']);
        redirect($redirectTo);
    }

    $error = 'Invalid email or password.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | <?= APP_NAME ?></title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <header class="topbar">
        <div class="container topbar-inner">
            <a class="brand" href="index.php">
                <span class="brand-mark">S</span>
                <span>StayEase</span>
            </a>
            <nav class="nav">
                <a href="index.php">Home</a>
                <a href="hotels.php">Hotels</a>
                <a href="register.php" class="btn btn-primary">Create account</a>
            </nav>
        </div>
    </header>

    <main class="container page-head">
        <div class="form-panel">
            <h1 style="margin-top:0;">Login to your account</h1>
            <?php if ($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <form method="post" class="form-grid">
                <div class="field field-full">
                    <label for="email">Email address</label>
                    <input type="email" id="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                </div>
                <div class="field field-full">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <div class="field-full">
                    <button type="submit" class="btn btn-primary">Login</button>
                </div>
            </form>
            <p style="margin-top:16px; color: var(--muted);">Demo admin: <strong>admin@stayease.com</strong> / <strong>admin123</strong></p>
        </div>
    </main>
</body>
</html>
