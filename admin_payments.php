<?php
require __DIR__ . '/includes/bootstrap.php';
requireRole(['admin', 'hotel_head_admin']);

$db = db();
$user = currentUser();
$managedHotelId = currentManagedHotelId();
$hotelAdmin = $user['role'] === 'hotel_head_admin';
if ($hotelAdmin && $managedHotelId === null) {
    http_response_code(403);
    exit('A hotel assignment is required.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['booking_id']) && !empty($_POST['status'])) {
    requireValidCsrfToken();
    $status = $_POST['status'];
    if (in_array($status, ['pending', 'confirmed', 'cancelled'], true)) {
        $updateSql = 'UPDATE bookings SET status = :status WHERE id = :id';
        $params = [
            ':status' => $status,
            ':id' => (int) $_POST['booking_id'],
        ];
        if ($managedHotelId !== null) {
            $updateSql .= ' AND hotel_id = :hotel_id';
            $params[':hotel_id'] = $managedHotelId;
        }
        $statement = $db->prepare($updateSql);
        $statement->execute($params);
    }
    redirect('admin_payments.php');
}

$bookingSql = 'SELECT b.*, h.name AS hotel_name, r.name AS room_name, u.name AS guest_name FROM bookings b JOIN hotels h ON h.id = b.hotel_id JOIN rooms r ON r.id = b.room_id JOIN users u ON u.id = b.user_id';
if ($managedHotelId !== null) {
    $bookingSql .= ' WHERE b.hotel_id = :hotel_id';
}
$bookingSql .= ' ORDER BY b.created_at DESC';
$bookings = $db->prepare($bookingSql);
if ($managedHotelId !== null) {
    $bookings->execute([':hotel_id' => $managedHotelId]);
} else {
    $bookings->execute();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Verification | <?= APP_NAME ?></title>
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
                <?php if (!$hotelAdmin): ?><a href="admin_customers.php">Users</a><?php endif; ?>
                <a href="admin_payments.php">Payments</a>
                <a href="profile.php">Profile</a>
                <a href="logout.php" class="btn btn-secondary">Logout</a>
            </nav>
        </div>
    </header>

    <main class="container page-head">
        <h1>Payment verification</h1>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Guest</th>
                        <th>Hotel</th>
                        <th>Room</th>
                        <th>Dates</th>
                        <th>Total</th>
                        <th>Payment proof</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($booking = $bookings->fetch()): ?>
                        <tr>
                            <td><?= htmlspecialchars($booking['guest_name']) ?></td>
                            <td><?= htmlspecialchars($booking['hotel_name']) ?></td>
                            <td><?= htmlspecialchars($booking['room_name']) ?></td>
                            <td><?= htmlspecialchars($booking['check_in']) ?> → <?= htmlspecialchars($booking['check_out']) ?></td>
                            <td><?= formatMoney((float) $booking['total_amount']) ?></td>
                            <td>
                                <?php if (!empty($booking['payment_proof'])): ?>
                                    <a href="<?= htmlspecialchars($booking['payment_proof']) ?>" target="_blank" rel="noreferrer">View file</a>
                                <?php else: ?>
                                    <span>—</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="status-pill status-<?= htmlspecialchars(strtolower($booking['status'])) ?>"><?= htmlspecialchars($booking['status']) ?></span></td>
                            <td>
                                <form method="post" style="display:flex; gap:8px; flex-wrap:wrap;">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
                                    <input type="hidden" name="booking_id" value="<?= (int) $booking['id'] ?>">
                                    <button type="submit" name="status" value="confirmed" class="btn btn-success">Confirm</button>
                                    <button type="submit" name="status" value="cancelled" class="btn btn-warning">Reject</button>
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
