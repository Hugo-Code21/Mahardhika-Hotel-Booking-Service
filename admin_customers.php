<?php
require __DIR__ . '/includes/bootstrap.php';
requireRole(['admin']);

$db = db();
$currentUser = currentUser();
$errors = [];
$formUser = [
    'id' => '',
    'name' => '',
    'email' => '',
    'phone' => '',
    'role' => 'customer',
    'hotel_id' => '',
];

if (isset($_GET['edit'])) {
    $editStatement = $db->prepare('SELECT id, name, email, phone, role, hotel_id FROM users WHERE id = :id');
    $editStatement->execute([':id' => (int) $_GET['edit']]);
    $existingUser = $editStatement->fetch();
    if ($existingUser) {
        $formUser = $existingUser;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrfToken();
    if (isset($_POST['delete_id'])) {
        $deleteId = (int) $_POST['delete_id'];
        if ($deleteId === (int) $currentUser['id']) {
            $errors[] = 'You cannot delete your own account.';
        } else {
            $targetStatement = $db->prepare('SELECT role FROM users WHERE id = :id');
            $targetStatement->execute([':id' => $deleteId]);
            $targetRole = $targetStatement->fetchColumn();
            $adminCount = (int) $db->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
            if ($targetRole === 'admin' && $adminCount <= 1) {
                $errors[] = 'The last Admin account cannot be deleted.';
            } else {
                $deleteStatement = $db->prepare('DELETE FROM users WHERE id = :id');
                $deleteStatement->execute([':id' => $deleteId]);
                redirect('admin_customers.php');
            }
        }
    } else {
        $formUser = [
            'id' => trim((string) ($_POST['user_id'] ?? '')),
            'name' => trim((string) ($_POST['name'] ?? '')),
            'email' => trim((string) ($_POST['email'] ?? '')),
            'phone' => trim((string) ($_POST['phone'] ?? '')),
            'role' => (string) ($_POST['role'] ?? ''),
            'hotel_id' => trim((string) ($_POST['hotel_id'] ?? '')),
        ];
        $password = (string) ($_POST['password'] ?? '');
        $roles = ['admin', 'customer', 'hotel_head_admin'];
        $userId = $formUser['id'] !== '' ? (int) $formUser['id'] : null;
        $hotelId = $formUser['hotel_id'] !== '' ? (int) $formUser['hotel_id'] : null;

        if ($formUser['name'] === '') {
            $errors[] = 'Enter the user name.';
        }
        if (!filter_var($formUser['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Enter a valid email address.';
        }
        if (!in_array($formUser['role'], $roles, true)) {
            $errors[] = 'Select one of the available user roles.';
        }
        if ($formUser['role'] === 'hotel_head_admin' && !$hotelId) {
            $errors[] = 'Choose a hotel for the Hotel Head Admin.';
        }
        if ($userId === null && strlen($password) < 8) {
            $errors[] = 'New accounts require a password with at least 8 characters.';
        }
        if ($password !== '' && strlen($password) < 8) {
            $errors[] = 'Passwords must contain at least 8 characters.';
        }
        if ($userId !== null && $userId === (int) $currentUser['id'] && $formUser['role'] !== 'admin') {
            $errors[] = 'Your own account must remain an Admin.';
        }
        if ($userId !== null && $formUser['role'] !== 'admin') {
            $roleStatement = $db->prepare('SELECT role FROM users WHERE id = :id');
            $roleStatement->execute([':id' => $userId]);
            if ($roleStatement->fetchColumn() === 'admin' && (int) $db->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn() <= 1) {
                $errors[] = 'The last Admin account cannot be reassigned to another role.';
            }
        }
        if ($formUser['role'] === 'hotel_head_admin' && $hotelId) {
            $hotelStatement = $db->prepare('SELECT id FROM hotels WHERE id = :id');
            $hotelStatement->execute([':id' => $hotelId]);
            if (!$hotelStatement->fetch()) {
                $errors[] = 'Choose an existing hotel.';
            }
        }

        $emailSql = 'SELECT id FROM users WHERE email = :email';
        $emailParams = [':email' => $formUser['email']];
        if ($userId !== null) {
            $emailSql .= ' AND id != :id';
            $emailParams[':id'] = $userId;
        }
        $emailStatement = $db->prepare($emailSql);
        $emailStatement->execute($emailParams);
        if ($emailStatement->fetch()) {
            $errors[] = 'That email address is already in use.';
        }

        if ($errors === []) {
            $hotelId = $formUser['role'] === 'hotel_head_admin' ? $hotelId : null;
            if ($userId === null) {
                $saveStatement = $db->prepare('INSERT INTO users (name, email, password, phone, role, hotel_id) VALUES (:name, :email, :password, :phone, :role, :hotel_id)');
                $saveStatement->execute([
                    ':name' => $formUser['name'],
                    ':email' => $formUser['email'],
                    ':password' => password_hash($password, PASSWORD_DEFAULT),
                    ':phone' => $formUser['phone'] !== '' ? $formUser['phone'] : null,
                    ':role' => $formUser['role'],
                    ':hotel_id' => $hotelId,
                ]);
            } elseif ($password !== '') {
                $saveStatement = $db->prepare('UPDATE users SET name = :name, email = :email, password = :password, phone = :phone, role = :role, hotel_id = :hotel_id WHERE id = :id');
                $saveStatement->execute([
                    ':name' => $formUser['name'],
                    ':email' => $formUser['email'],
                    ':password' => password_hash($password, PASSWORD_DEFAULT),
                    ':phone' => $formUser['phone'] !== '' ? $formUser['phone'] : null,
                    ':role' => $formUser['role'],
                    ':hotel_id' => $hotelId,
                    ':id' => $userId,
                ]);
            } else {
                $saveStatement = $db->prepare('UPDATE users SET name = :name, email = :email, phone = :phone, role = :role, hotel_id = :hotel_id WHERE id = :id');
                $saveStatement->execute([
                    ':name' => $formUser['name'],
                    ':email' => $formUser['email'],
                    ':phone' => $formUser['phone'] !== '' ? $formUser['phone'] : null,
                    ':role' => $formUser['role'],
                    ':hotel_id' => $hotelId,
                    ':id' => $userId,
                ]);
            }

            redirect('admin_customers.php');
        }
    }
}

$hotels = $db->query('SELECT id, name, city FROM hotels ORDER BY name')->fetchAll();
$users = $db->query('SELECT u.id, u.name, u.email, u.phone, u.role, u.hotel_id, h.name AS hotel_name FROM users u LEFT JOIN hotels h ON h.id = u.hotel_id ORDER BY u.role, u.created_at DESC')->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users | <?= APP_NAME ?></title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <header class="topbar">
        <div class="container topbar-inner">
            <a class="brand" href="index.php"><span class="brand-mark">S</span><span>StayEase</span></a>
            <nav class="nav">
                <a href="admin.php">Dashboard</a>
                <a href="admin_hotels.php">Hotels</a>
                <a href="admin_rooms.php">Rooms</a>
                <a href="admin_customers.php">Users</a>
                <a href="admin_payments.php">Payments</a>
                <a href="profile.php">Profile</a>
                <a href="logout.php" class="btn btn-secondary">Logout</a>
            </nav>
        </div>
    </header>

    <main class="container page-head">
        <h1>User management</h1>
        <p>Create, update, and remove Admin, Customer, and Hotel Head Admin accounts.</p>

        <div class="form-panel">
            <h2 style="margin-top:0;"><?= $formUser['id'] !== '' ? 'Update user' : 'Create user' ?></h2>
            <?php foreach ($errors as $error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endforeach; ?>
            <form method="post" class="form-grid">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
                <input type="hidden" name="user_id" value="<?= (int) $formUser['id'] ?>">
                <div class="field">
                    <label for="name">Name</label>
                    <input type="text" id="name" name="name" maxlength="160" value="<?= htmlspecialchars($formUser['name']) ?>" required>
                </div>
                <div class="field">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" maxlength="254" value="<?= htmlspecialchars($formUser['email']) ?>" required>
                </div>
                <div class="field">
                    <label for="phone">Phone</label>
                    <input type="text" id="phone" name="phone" maxlength="40" value="<?= htmlspecialchars($formUser['phone'] ?? '') ?>">
                </div>
                <div class="field">
                    <label for="role">Role</label>
                    <select id="role" name="role" required>
                        <option value="admin" <?= $formUser['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                        <option value="customer" <?= $formUser['role'] === 'customer' ? 'selected' : '' ?>>Customer</option>
                        <option value="hotel_head_admin" <?= $formUser['role'] === 'hotel_head_admin' ? 'selected' : '' ?>>Hotel Head Admin</option>
                    </select>
                </div>
                <div class="field">
                    <label for="hotel_id">Assigned hotel (Hotel Head Admin only)</label>
                    <select id="hotel_id" name="hotel_id">
                        <option value="">No hotel assignment</option>
                        <?php foreach ($hotels as $hotel): ?>
                            <option value="<?= (int) $hotel['id'] ?>" <?= (int) $formUser['hotel_id'] === (int) $hotel['id'] ? 'selected' : '' ?>><?= htmlspecialchars($hotel['name'] . ' — ' . $hotel['city']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label for="password">Password <?= $formUser['id'] !== '' ? '(leave blank to keep current)' : '(minimum 8 characters)' ?></label>
                    <input type="password" id="password" name="password" minlength="8" <?= $formUser['id'] === '' ? 'required' : '' ?> autocomplete="new-password">
                </div>
                <div class="field-full">
                    <button type="submit" class="btn btn-primary"><?= $formUser['id'] !== '' ? 'Update user' : 'Create user' ?></button>
                    <?php if ($formUser['id'] !== ''): ?>
                        <a href="admin_customers.php" class="btn btn-secondary">Cancel edit</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <div class="page-head" style="padding-top:24px;">
            <h2>Accounts</h2>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th>Name</th><th>Email</th><th>Phone</th><th>Role</th><th>Hotel</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $account): ?>
                        <tr>
                            <td><?= htmlspecialchars($account['name']) ?></td>
                            <td><?= htmlspecialchars($account['email']) ?></td>
                            <td><?= htmlspecialchars($account['phone'] ?? '-') ?></td>
                            <td><?= htmlspecialchars(['admin' => 'Admin', 'customer' => 'Customer', 'hotel_head_admin' => 'Hotel Head Admin'][$account['role']] ?? $account['role']) ?></td>
                            <td><?= htmlspecialchars($account['hotel_name'] ?? '—') ?></td>
                            <td>
                                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                                    <a class="btn btn-secondary" href="admin_customers.php?edit=<?= (int) $account['id'] ?>">Edit</a>
                                    <?php if ((int) $account['id'] !== (int) $currentUser['id']): ?>
                                        <form method="post" onsubmit="return confirm('Delete this user account? Related bookings and notifications will also be removed.');">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
                                            <input type="hidden" name="delete_id" value="<?= (int) $account['id'] ?>">
                                            <button type="submit" class="btn btn-warning">Delete</button>
                                        </form>
                                    <?php else: ?>
                                        <span>Current account</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>
</body>
</html>
