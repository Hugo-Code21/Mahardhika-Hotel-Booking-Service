<?php
require __DIR__ . '/includes/bootstrap.php';

$city = trim($_GET['city'] ?? '');
$checkin = $_GET['checkin'] ?? date('Y-m-d', strtotime('+2 days'));
$checkout = $_GET['checkout'] ?? date('Y-m-d', strtotime('+5 days'));
$guests = max(1, (int) ($_GET['guests'] ?? 2));

$db = db();
$query = 'SELECT * FROM hotels';
$params = [];
if ($city !== '') {
    $query .= ' WHERE city LIKE :city';
}
$query .= ' ORDER BY rating DESC';

$statement = $db->prepare($query);
if ($city !== '') {
    $statement->bindValue(':city', '%' . $city . '%', PDO::PARAM_STR);
}
$statement->execute();
$result = $statement;
$currentUser = currentUser();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hotels | <?= APP_NAME ?></title>
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
        <div class="section-header-row">
            <div>
                <h1>Available hotels</h1>
                <p>Showing hotels in <?= htmlspecialchars($city !== '' ? $city : 'all destinations') ?> for <?= htmlspecialchars($checkin) ?> to <?= htmlspecialchars($checkout) ?>.</p>
            </div>
            <div class="mini-summary-box">
                <span>Stay range</span>
                <strong><?= htmlspecialchars($checkin) ?> → <?= htmlspecialchars($checkout) ?></strong>
            </div>
        </div>

        <div class="form-panel booking-search-panel">
            <form method="get" action="hotels.php" class="search-form">
                <div class="field">
                    <label for="city">Destination</label>
                    <input type="text" id="city" name="city" value="<?= htmlspecialchars($city ?: 'Bandung') ?>">
                </div>
                <div class="field">
                    <label for="guests">Guests</label>
                    <select id="guests" name="guests">
                        <?php for ($i = 1; $i <= 6; $i++): ?>
                            <option value="<?= $i ?>" <?= $i == $guests ? 'selected' : '' ?>><?= $i ?> guest<?= $i > 1 ? 's' : '' ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="field">
                    <label for="checkin">Check-in</label>
                    <input type="date" id="checkin" name="checkin" value="<?= htmlspecialchars($checkin) ?>">
                </div>
                <div class="field">
                    <label for="checkout">Check-out</label>
                    <input type="date" id="checkout" name="checkout" value="<?= htmlspecialchars($checkout) ?>">
                </div>
                <div class="field field-full">
                    <button class="btn btn-primary" type="submit">Update results</button>
                </div>
            </form>
        </div>

        <div class="experience-strip">
            <div class="experience-box">
                <span class="experience-label">Curated stays</span>
                <strong>Thoughtful spaces</strong>
            </div>
            <div class="experience-box">
                <span class="experience-label">Flexible check-in</span>
                <strong>Easy arrival</strong>
            </div>
            <div class="experience-box">
                <span class="experience-label">Guest favorite</span>
                <strong>Top rated stays</strong>
            </div>
        </div>

        <div class="section">
            <div class="hotel-list">
                <?php while ($hotel = $result->fetch()): ?>
                    <?php
                    $roomResult = $db->query('SELECT * FROM rooms WHERE hotel_id = ' . (int) $hotel['id']);
                    $roomCount = 0;
                    $availableRooms = 0;
                    while ($room = $roomResult->fetch()) {
                        $roomCount++;
                        if (isRoomAvailable((int) $room['id'], $checkin, $checkout)) {
                            $availableRooms++;
                        }
                    }
                    ?>
                    <article class="hotel-card">
                        <div class="hotel-image-wrap">
                            <img src="<?= htmlspecialchars($hotel['image']) ?>" alt="<?= htmlspecialchars($hotel['name']) ?>">
                        </div>
                        <div class="hotel-info">
                            <div class="hotel-topline">
                                <div>
                                    <h3><?= htmlspecialchars($hotel['name']) ?></h3>
                                    <div class="hotel-location"><?= htmlspecialchars($hotel['city']) ?> · <?= htmlspecialchars($hotel['address'] ?: 'City center') ?></div>
                                </div>
                                <div class="rating-badge">★ <?= number_format((float) $hotel['rating'], 1) ?></div>
                            </div>

                            <div class="hotel-tags">
                                <span class="hotel-pill">Free cancellation</span>
                                <span class="hotel-pill">Breakfast</span>
                                <span class="hotel-pill">Wi‑Fi</span>
                            </div>

                            <p><?= htmlspecialchars($hotel['description']) ?></p>

                            <div class="hotel-meta">
                                <span><?= $roomCount ?> room<?= $roomCount > 1 ? 's' : '' ?></span>
                                <span><?= $availableRooms ?> available now</span>
                            </div>
                        </div>

                        <div class="hotel-booking">
                            <div class="booking-price">
                                <span>from</span>
                                <strong><?= formatMoney(450000) ?></strong>
                            </div>
                            <div class="booking-note">Includes taxes & fees</div>
                            <div class="booking-actions">
                                <a href="reviews.php?hotel_id=<?= (int) $hotel['id'] ?>" class="btn btn-secondary">Reviews</a>
                                <a href="rooms.php?hotel_id=<?= (int) $hotel['id'] ?>&checkin=<?= urlencode($checkin) ?>&checkout=<?= urlencode($checkout) ?>&guests=<?= (int) $guests ?>" class="btn btn-primary">View rooms</a>
                            </div>
                        </div>
                    </article>
                <?php endwhile; ?>
            </div>
        </div>
    </main>
</body>

</html>