<?php
require __DIR__ . '/includes/bootstrap.php';
requireAuth();

$user = currentUser();
$bookingId = (int) ($_GET['id'] ?? 0);

if ($bookingId <= 0) {
    redirect('profile.php');
}

$db = db();
$booking = $db->prepare('SELECT * FROM bookings WHERE id = :id AND user_id = :user_id');
$booking->execute([':id' => $bookingId, ':user_id' => (int) $user['id']]);
$record = $booking->fetch();

if (!$record) {
    redirect('profile.php');
}

if (!in_array($record['status'], ['pending', 'confirmed'], true)) {
    redirect('profile.php');
}

$db->prepare('UPDATE bookings SET status = :status WHERE id = :id')->execute([
    ':status' => 'cancelled',
    ':id' => $bookingId,
]);

addNotification((int) $user['id'], 'Your booking ' . ($record['booking_code'] ?? 'STAY') . ' has been cancelled successfully.', $bookingId);

redirect('profile.php');
