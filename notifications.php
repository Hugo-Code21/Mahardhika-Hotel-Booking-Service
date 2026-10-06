<?php
require __DIR__ . '/includes/bootstrap.php';
requireAuth();

$user = currentUser();
$db = db();
$notifications = $db->prepare('SELECT * FROM notifications WHERE user_id = :user_id ORDER BY created_at DESC LIMIT 25');
$notifications->execute([':user_id' => (int) $user['id']]);
$unread = (int) $db->query('SELECT COUNT(*) FROM notifications WHERE user_id = ' . (int) $user['id'] . ' AND is_read = 0')->fetchColumn();
$db->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = :user_id')->execute([':user_id' => (int) $user['id']]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications | <?= APP_NAME ?></title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <header class="topbar">
        <div class="container topbar-inner">
            <a class="brand" href="index.php"><span class="brand-mark">S</span><span>StayEase</span></a>
            <nav class="nav">
                <a href="index.php">Home</a>
                <a href="hotels.php">Hotels</a>
                <a href="profile.php">Profile</a>
                <a href="logout.php" class="btn btn-secondary">Logout</a>
            </nav>
        </div>
    </header>

    <main class="container page-head">
        <h1>Notifications</h1>
        <p>You have <?= $unread ?> unread message(s).</p>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Message</th>
                        <th>Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($item = $notifications->fetch()): ?>
                        <tr>
                            <td><?= htmlspecialchars($item['message']) ?></td>
                            <td><?= htmlspecialchars($item['created_at']) ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </main>
</body>
</html>
