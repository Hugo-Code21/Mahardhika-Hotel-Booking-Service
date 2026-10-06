<?php
require __DIR__ . '/includes/bootstrap.php';
requireAuth();

$user = currentUser();
$db = db();
$bookings = $db->query('SELECT b.*, h.name AS hotel_name, r.name AS room_name FROM bookings b JOIN rooms r ON r.id = b.room_id JOIN hotels h ON h.id = b.hotel_id WHERE b.user_id = ' . (int) $user['id'] . ' ORDER BY b.created_at DESC');
$activeBooking = null;
$history = [];
while ($booking = $bookings->fetch()) {
    if (in_array($booking['status'], ['pending', 'confirmed', 'checked_in'], true)) {
        $activeBooking ??= $booking;
    }
    $history[] = $booking;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Dashboard | <?= APP_NAME ?></title>
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
                <a href="notifications.php">Notifications</a>
                <a href="logout.php" class="btn btn-secondary">Logout</a>
            </nav>
        </div>
    </header>

    <main class="container page-head">
        <div class="profile-hero">
            <div>
                <span class="eyebrow">Private account</span>
                <h1>Welcome back, <?= htmlspecialchars($user['name']) ?> 👋</h1>
            </div>
            <div class="profile-card-mini">
                <span>Member since</span>
                <strong><?= htmlspecialchars(date('M d, Y', strtotime($user['created_at'] ?? 'now'))) ?></strong>
            </div>
        </div>

        <div class="profile-layout">
            <div class="form-panel profile-panel">
                <h3>Guest profile</h3>
                <div class="summary-row"><span>Name</span><strong><?= htmlspecialchars($user['name']) ?></strong></div>
                <div class="summary-row"><span>Email</span><strong><?= htmlspecialchars($user['email']) ?></strong></div>
                <div class="summary-row"><span>Phone</span><strong><?= htmlspecialchars($user['phone'] ?: 'Not provided') ?></strong></div>
                <div class="summary-row"><span>Role</span><strong><?= htmlspecialchars(ucfirst($user['role'])) ?></strong></div>
            </div>

            <div class="form-panel profile-panel">
                <h3>Travel preferences</h3>
                <div class="preference-list">
                    <span>City escapes</span>
                    <span>Wellness stays</span>
                    <span>Family trips</span>
                    <span>Quiet luxury</span>
                </div>
                <div class="profile-note">Your saved preferences make it easier to discover stays that match your style.</div>
            </div>
        </div>

        <div class="page-head" style="padding-top: 14px;">
            <h2>Active booking</h2>
        </div>

        <?php if ($activeBooking): ?>
            <div class="form-panel booking-compact-panel">
                <div class="summary-row"><span>Hotel</span><strong><?= htmlspecialchars($activeBooking['hotel_name']) ?></strong></div>
                <div class="summary-row"><span>Room</span><strong><?= htmlspecialchars($activeBooking['room_name']) ?></strong></div>
                <div class="summary-row"><span>Dates</span><strong><?= htmlspecialchars($activeBooking['check_in']) ?> → <?= htmlspecialchars($activeBooking['check_out']) ?></strong></div>
                <div class="summary-row"><span>Status</span><strong><span class="status-pill status-<?= htmlspecialchars(strtolower($activeBooking['status'])) ?>"><?= htmlspecialchars($activeBooking['status']) ?></span></strong></div>
                <div class="summary-row"><span>Booking code</span><strong><?= htmlspecialchars($activeBooking['booking_code'] ?? 'STAY') ?></strong></div>
                <?php if (!in_array($activeBooking['status'], ['cancelled', 'checked_in'], true)): ?>
                    <div style="margin-top:16px;">
                        <a href="cancel_booking.php?id=<?= (int) $activeBooking['id'] ?>" class="btn btn-warning">Cancel booking</a>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-success">No active booking right now. Start by searching a hotel.</div>
        <?php endif; ?>

        <div class="page-head" style="padding-top: 20px;">
            <h2>Booking history</h2>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Booking code</th>
                        <th>Hotel</th>
                        <th>Room</th>
                        <th>Dates</th>
                        <th>Total</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($history as $booking): ?>
                        <tr>
                            <td><?= htmlspecialchars($booking['booking_code'] ?? 'STAY') ?></td>
                            <td><?= htmlspecialchars($booking['hotel_name']) ?></td>
                            <td><?= htmlspecialchars($booking['room_name']) ?></td>
                            <td><?= htmlspecialchars($booking['check_in']) ?> → <?= htmlspecialchars($booking['check_out']) ?></td>
                            <td><?= formatMoney((float) $booking['total_amount']) ?></td>
                            <td><span class="status-pill status-<?= htmlspecialchars(strtolower($booking['status'])) ?>"><?= htmlspecialchars($booking['status']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>
</body>
</html>
