<?php
require __DIR__ . '/includes/bootstrap.php';

requireRole(['admin']);

$db = db();
$today = date('Y-m-d');
$checkIns = $db->query("SELECT b.*, h.name AS hotel_name, r.name AS room_name, u.name AS guest_name FROM bookings b JOIN hotels h ON h.id = b.hotel_id JOIN rooms r ON r.id = b.room_id JOIN users u ON u.id = b.user_id WHERE b.check_in = '$today' AND b.status != 'cancelled' ORDER BY b.created_at DESC");
$checkOuts = $db->query("SELECT b.*, h.name AS hotel_name, r.name AS room_name, u.name AS guest_name FROM bookings b JOIN hotels h ON h.id = b.hotel_id JOIN rooms r ON r.id = b.room_id JOIN users u ON u.id = b.user_id WHERE b.check_out = '$today' AND b.status != 'cancelled' ORDER BY b.created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reception Desk | <?= APP_NAME ?></title>
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
                <a href="admin.php">Admin</a>
                <a href="profile.php">Profile</a>
                <a href="logout.php" class="btn btn-secondary">Logout</a>
            </nav>
        </div>
    </header>

    <main class="container page-head">
        <h1>Reception desk</h1>
        <p>Today's check-ins and check-outs for guest operations.</p>

        <div class="section">
            <div class="grid">
                <div class="card">
                    <div class="card-body">
                        <h3>Today's check-ins</h3>
                        <ul>
                            <?php while ($booking = $checkIns->fetch()): ?>
                                <li><?= htmlspecialchars($booking['guest_name']) ?> · <?= htmlspecialchars($booking['hotel_name']) ?> · <?= htmlspecialchars($booking['room_name']) ?></li>
                            <?php endwhile; ?>
                        </ul>
                    </div>
                </div>
                <div class="card">
                    <div class="card-body">
                        <h3>Today's check-outs</h3>
                        <ul>
                            <?php while ($booking = $checkOuts->fetch()): ?>
                                <li><?= htmlspecialchars($booking['guest_name']) ?> · <?= htmlspecialchars($booking['hotel_name']) ?> · <?= htmlspecialchars($booking['room_name']) ?></li>
                            <?php endwhile; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
