<?php
require __DIR__ . '/includes/bootstrap.php';

requireRole(['admin', 'hotel_head_admin']);

$db = db();
$user = currentUser();
$hotelId = currentManagedHotelId();
$hotelAdmin = $user['role'] === 'hotel_head_admin';
if ($hotelAdmin && $hotelId === null) {
    http_response_code(403);
    exit('A hotel assignment is required.');
}
$hotelFilter = $hotelId === null ? '' : ' WHERE hotel_id = :hotel_id';
$managedHotelFilter = $hotelId === null ? '' : ' WHERE id = :hotel_id';
$count = static function (string $table, string $filter) use ($db, $hotelId): int {
    $statement = $db->prepare('SELECT COUNT(*) FROM ' . $table . $filter);
    if ($hotelId !== null) {
        $statement->execute([':hotel_id' => $hotelId]);
    } else {
        $statement->execute();
    }

    return (int) $statement->fetchColumn();
};
$stats = [
    'total_hotels' => $count('hotels', $managedHotelFilter),
    'total_rooms' => $count('rooms', $hotelFilter),
    'total_bookings' => $count('bookings', $hotelFilter),
    'pending_payments' => $count('bookings', ($hotelFilter === '' ? ' WHERE ' : $hotelFilter . ' AND ') . "status = 'pending'"),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['booking_id']) && !empty($_POST['status'])) {
    requireValidCsrfToken();
    $status = (string) $_POST['status'];
    if (in_array($status, ['pending', 'confirmed', 'cancelled'], true)) {
        $updateSql = 'UPDATE bookings SET status = :status WHERE id = :id';
        $params = [':status' => $status, ':id' => (int) $_POST['booking_id']];
        if ($hotelId !== null) {
            $updateSql .= ' AND hotel_id = :hotel_id';
            $params[':hotel_id'] = $hotelId;
        }
        $statement = $db->prepare($updateSql);
        $statement->execute($params);
    }
    header('Location: admin.php');
    exit;
}

$bookingSql = 'SELECT b.*, h.name AS hotel_name, r.name AS room_name, u.name AS guest_name FROM bookings b JOIN hotels h ON h.id = b.hotel_id JOIN rooms r ON r.id = b.room_id JOIN users u ON u.id = b.user_id';
if ($hotelId !== null) {
    $bookingSql .= ' WHERE b.hotel_id = :hotel_id';
}
$bookingSql .= ' ORDER BY b.created_at DESC LIMIT 25';
$bookings = $db->prepare($bookingSql);
if ($hotelId !== null) {
    $bookings->execute([':hotel_id' => $hotelId]);
} else {
    $bookings->execute();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | <?= APP_NAME ?></title>
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
                <a href="admin_hotels.php">Hotels</a>
                <a href="admin_rooms.php">Rooms</a>
                <?php if (!$hotelAdmin): ?><a href="admin_customers.php">Users</a><?php endif; ?>
                <a href="admin_payments.php">Payments</a>
                <a href="profile.php">Profile</a>
                <a href="logout.php" class="btn btn-secondary">Logout</a>
            </nav>
        </div>
    </header>

    <main class="container page-head">
        <div class="admin-hero">
            <div>
                <span class="eyebrow">Operations overview</span>
                <h1><?= $hotelId === null ? 'Hotel admin dashboard' : 'Hotel operations dashboard' ?></h1>
                <p>Reservation overview, payment verification, and room status management.</p>
            </div>
            <div class="admin-hero-badge">
                <span>Today</span>
                <strong><?= date('M d, Y') ?></strong>
            </div>
        </div>

        <div class="stats">
            <div class="stat">
                <strong><?= (int) $stats['total_hotels'] ?></strong>
                <span>Hotels</span>
            </div>
            <div class="stat">
                <strong><?= (int) $stats['total_rooms'] ?></strong>
                <span>Rooms</span>
            </div>
            <div class="stat">
                <strong><?= (int) $stats['total_bookings'] ?></strong>
                <span>Bookings</span>
            </div>
            <div class="stat">
                <strong><?= (int) $stats['pending_payments'] ?></strong>
                <span>Pending</span>
            </div>
        </div>

        <div class="page-head" style="padding-top: 30px;">
            <h2>Recent reservations</h2>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Guest</th>
                        <th>Hotel</th>
                        <th>Room</th>
                        <th>Dates</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($booking = $bookings->fetch()): ?>
                        <tr>
                            <td><?= htmlspecialchars($booking['guest_name']) ?></td>
                            <td><?= htmlspecialchars($booking['hotel_name']) ?></td>
                            <td><?= htmlspecialchars($booking['room_name']) ?></td>
                            <td><?= htmlspecialchars($booking['check_in']) ?> → <?= htmlspecialchars($booking['check_out']) ?></td>
                            <td>
                                <span class="status-pill status-<?= htmlspecialchars(strtolower($booking['status'])) ?>"><?= htmlspecialchars($booking['status']) ?></span>
                            </td>
                            <td>
                                <form method="post" style="display:flex; gap:8px;">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
                                    <input type="hidden" name="booking_id" value="<?= (int) $booking['id'] ?>">
                                    <select name="status">
                                        <option value="pending" <?= $booking['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                                        <option value="confirmed" <?= $booking['status'] === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                                        <option value="cancelled" <?= $booking['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                    </select>
                                    <button type="submit" class="btn btn-warning">Update</button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </main>
</body>
</html>
