<?php
require __DIR__ . '/includes/bootstrap.php';

if (currentUser()) {
    redirect('index.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($name === '' || $email === '' || $password === '') {
        $error = 'Please complete all required fields.';
    } else {
        $check = db()->prepare('SELECT id FROM users WHERE email = :email');
        $check->execute([':email' => $email]);
        $existing = $check->fetch();

        if ($existing) {
            $error = 'An account already exists for this email.';
        } else {
            $statement = db()->prepare('INSERT INTO users (name, email, phone, password, role) VALUES (:name, :email, :phone, :password, :role)');
            $statement->execute([
                ':name' => $name,
                ':email' => $email,
                ':phone' => $phone,
                ':password' => password_hash($password, PASSWORD_DEFAULT),
                ':role' => 'customer',
            ]);
            redirect('login.php');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | <?= APP_NAME ?></title>
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
                <a href="login.php" class="btn btn-secondary">Login</a>
            </nav>
        </div>
    </header>

    <main class="container page-head">
        <div class="form-panel">
            <h1 style="margin-top:0;">Create your account</h1>
            <?php if ($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <form method="post" class="form-grid">
                <div class="field">
                    <label for="name">Full name</label>
                    <input type="text" id="name" name="name" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>
                </div>
                <div class="field">
                    <label for="phone">Phone number</label>
                    <input type="tel" id="phone" name="phone" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                </div>
                <div class="field field-full">
                    <label for="email">Email address</label>
                    <input type="email" id="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                </div>
                <div class="field field-full">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <div class="field-full">
                    <button class="btn btn-primary" type="submit">Register</button>
                </div>
            </form>
        </div>
    </main>
</body>
</html>
