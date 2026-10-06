<?php
require __DIR__ . '/includes/bootstrap.php';

$db = db();
$hotelId = (int) ($_GET['hotel_id'] ?? 0);
$currentUser = currentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $currentUser) {
    $hotelIdInput = (int) ($_POST['hotel_id'] ?? 0);
    $rating = max(1, min(5, (int) ($_POST['rating'] ?? 5)));
    $comment = trim($_POST['comment'] ?? '');

    $allowed = $db->prepare('SELECT id FROM bookings WHERE user_id = :user_id AND hotel_id = :hotel_id AND status = :status LIMIT 1');
    $allowed->execute([
        ':user_id' => (int) $currentUser['id'],
        ':hotel_id' => $hotelIdInput,
        ':status' => 'confirmed',
    ]);

    if ($allowed->fetch()) {
        $statement = $db->prepare('INSERT INTO reviews (hotel_id, user_id, rating, comment) VALUES (:hotel_id, :user_id, :rating, :comment)');
        $statement->execute([
            ':hotel_id' => $hotelIdInput,
            ':user_id' => (int) $currentUser['id'],
            ':rating' => $rating,
            ':comment' => $comment,
        ]);
        redirect('reviews.php?hotel_id=' . $hotelIdInput);
    }
}

$hotel = $hotelId > 0 ? getHotelById($hotelId) : null;
$reviews = $db->query('SELECT r.*, u.name AS user_name FROM reviews r JOIN users u ON u.id = r.user_id WHERE r.hotel_id = ' . $hotelId . ' ORDER BY r.created_at DESC');
$availableHotels = $db->query('SELECT * FROM hotels ORDER BY name');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reviews | <?= APP_NAME ?></title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <header class="topbar">
        <div class="container topbar-inner">
            <a class="brand" href="index.php"><span class="brand-mark">S</span><span>StayEase</span></a>
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
        <h1>Guest reviews</h1>

        <div class="form-panel">
            <form method="get" class="search-form">
                <div class="field field-full">
                    <label for="hotel_id">Choose hotel</label>
                    <select id="hotel_id" name="hotel_id" onchange="this.form.submit()">
                        <option value="0">Select hotel</option>
                        <?php while ($availableHotel = $availableHotels->fetch()): ?>
                            <option value="<?= (int) $availableHotel['id'] ?>" <?= $availableHotel['id'] == $hotelId ? 'selected' : '' ?>><?= htmlspecialchars($availableHotel['name']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </form>

            <?php if ($hotel): ?>
                <div class="booking-summary" style="margin-top: 20px;">
                    <div class="summary-row"><span>Hotel</span><strong><?= htmlspecialchars($hotel['name']) ?></strong></div>
                    <div class="summary-row"><span>Average rating</span><strong><?= number_format((float) $hotel['rating'], 1) ?> / 5</strong></div>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($currentUser && $hotel): ?>
            <div class="form-panel" style="margin-top: 20px;">
                <h2 style="margin-top:0;">Write a review</h2>
                <form method="post" class="form-grid">
                    <input type="hidden" name="hotel_id" value="<?= (int) $hotelId ?>">
                    <div class="field">
                        <label for="rating">Rating</label>
                        <select id="rating" name="rating">
                            <option value="5">5 - Excellent</option>
                            <option value="4">4 - Good</option>
                            <option value="3">3 - Fair</option>
                            <option value="2">2 - Poor</option>
                            <option value="1">1 - Bad</option>
                        </select>
                    </div>
                    <div class="field field-full">
                        <label for="comment">Comment</label>
                        <textarea id="comment" name="comment" placeholder="Share your stay experience"></textarea>
                    </div>
                    <div class="field-full">
                        <button type="submit" class="btn btn-primary">Submit review</button>
                    </div>
                </form>
            </div>
        <?php endif; ?>

        <div class="section">
            <div class="grid">
                <?php while ($review = $reviews->fetch()): ?>
                    <article class="card">
                        <div class="card-body">
                            <h3><?= htmlspecialchars($review['user_name']) ?></h3>
                            <div class="meta">
                                <span><?= str_repeat('★', (int) $review['rating']) ?><?= str_repeat('☆', 5 - (int) $review['rating']) ?></span>
                                <span><?= htmlspecialchars(date('M d, Y', strtotime($review['created_at']))) ?></span>
                            </div>
                            <p><?= htmlspecialchars($review['comment'] ?: 'No comments provided.') ?></p>
                        </div>
                    </article>
                <?php endwhile; ?>
            </div>
        </div>
    </main>
</body>
</html>
