<?php
require __DIR__ . '/includes/bootstrap.php';

$hotelId = (int) ($_GET['hotel_id'] ?? 0);
$checkin = $_GET['checkin'] ?? date('Y-m-d', strtotime('+2 days'));
$checkout = $_GET['checkout'] ?? date('Y-m-d', strtotime('+5 days'));
$guests = max(1, (int) ($_GET['guests'] ?? 2));

$hotel = getHotelById($hotelId);
if (!$hotel) {
    redirect('hotels.php');
}

$db = db();
$rooms = $db->query('SELECT * FROM rooms WHERE hotel_id = ' . $hotelId . ' ORDER BY price_per_night ASC');
$currentUser = currentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($hotel['name']) ?> | <?= APP_NAME ?></title>
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
                <?php if ($currentUser): ?>
                    <a href="profile.php">Profile</a>
                    <a href="logout.php" class="btn btn-secondary">Logout</a>
                <?php else: ?>
                    <a href="login.php" class="btn btn-secondary">Login</a>
                    <a href="register.php" class="btn btn-primary">Register</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>

    <main class="container page-head">
        <div class="hotel-detail-hero">
            <div class="hotel-detail-image">
                <img src="<?= htmlspecialchars($hotel['image']) ?>" alt="<?= htmlspecialchars($hotel['name']) ?>">
            </div>
            <div class="hotel-detail-copy">
                <span class="eyebrow">Boutique escape</span>
                <h1><?= htmlspecialchars($hotel['name']) ?></h1>
                <p><?= htmlspecialchars($hotel['city']) ?> · <?= htmlspecialchars($hotel['address']) ?> · ⭐ <?= number_format((float) $hotel['rating'], 1) ?></p>
                <div class="hotel-detail-metrics">
                    <div><strong><?= htmlspecialchars($hotel['city']) ?></strong><span>Location</span></div>
                    <div><strong><?= number_format((float) $hotel['rating'], 1) ?></strong><span>Guest rating</span></div>
                    <div><strong><?= $db->query('SELECT COUNT(*) FROM rooms WHERE hotel_id = ' . (int) $hotel['id'])->fetchColumn() ?></strong><span>Rooms</span></div>
                </div>
            </div>
        </div>

        <div class="form-panel">
            <div class="booking-summary">
                <div class="summary-row"><span>Check-in</span><strong><?= htmlspecialchars($checkin) ?></strong></div>
                <div class="summary-row"><span>Check-out</span><strong><?= htmlspecialchars($checkout) ?></strong></div>
                <div class="summary-row"><span>Guests</span><strong><?= (int) $guests ?></strong></div>
            </div>
        </div>

        <div class="amenity-grid">
            <div class="amenity-item"><strong>Breakfast</strong><span>Daily morning service</span></div>
            <div class="amenity-item"><strong>Wi‑Fi</strong><span>Complimentary high speed</span></div>
            <div class="amenity-item"><strong>Pool access</strong><span>Relaxed leisure time</span></div>
            <div class="amenity-item"><strong>Easy arrivals</strong><span>Check-in assistance</span></div>
        </div>

        <div class="section">
            <div class="room-list">
                <?php while ($room = $rooms->fetch()): ?>
                    <?php $available = isRoomAvailable((int) $room['id'], $checkin, $checkout); ?>
                    <article class="room-card">
                        <img src="<?= htmlspecialchars($room['image']) ?>" alt="<?= htmlspecialchars($room['name']) ?>">
                        <div class="room-card-body">
                            <h3><?= htmlspecialchars($room['name']) ?></h3>
                            <div class="meta">
                                <span><?= htmlspecialchars($room['bed_type']) ?></span>
                                <span><?= (int) $room['max_guests'] ?> guests</span>
                            </div>
                            <div class="room-features">
                                <?php foreach (explode(',', $room['facilities']) as $facility): ?>
                                    <span><?= htmlspecialchars(trim($facility)) ?></span>
                                <?php endforeach; ?>
                            </div>
                            <div class="price">
                                <strong><?= formatMoney((float) $room['price_per_night']) ?> / night</strong>
                                <?php if ($available): ?>
                                    <a href="booking.php?room_id=<?= (int) $room['id'] ?>&hotel_id=<?= $hotelId ?>&checkin=<?= urlencode($checkin) ?>&checkout=<?= urlencode($checkout) ?>&guests=<?= (int) $guests ?>" class="btn btn-primary">Book now</a>
                                <?php else: ?>
                                    <span class="status-pill status-cancelled">Unavailable</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </article>
                <?php endwhile; ?>
            </div>
        </div>
    </main>
</body>
</html>
