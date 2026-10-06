<?php
require __DIR__ . '/includes/bootstrap.php';

$roomId = (int) ($_GET['room_id'] ?? 0);
$hotelId = (int) ($_GET['hotel_id'] ?? 0);
$checkin = $_GET['checkin'] ?? date('Y-m-d', strtotime('+2 days'));
$checkout = $_GET['checkout'] ?? date('Y-m-d', strtotime('+5 days'));
$guests = max(1, (int) ($_GET['guests'] ?? 2));

$currentUser = currentUser();
if (!$currentUser) {
    $_SESSION['redirect_after_login'] = 'booking.php?room_id=' . $roomId . '&hotel_id=' . $hotelId . '&checkin=' . urlencode($checkin) . '&checkout=' . urlencode($checkout) . '&guests=' . $guests;
    redirect('login.php');
}

$room = getRoomById($roomId);
$hotel = getHotelById($hotelId);
if (!$room || !$hotel) {
    redirect('hotels.php');
}

$error = '';
$success = false;
$bookingId = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['guest_name'] ?? '');
    $email = trim($_POST['guest_email'] ?? '');
    $phone = trim($_POST['guest_phone'] ?? '');
    $paymentProofPath = null;

    if (!isRoomAvailable($roomId, $_POST['checkin'] ?? $checkin, $_POST['checkout'] ?? $checkout)) {
        $error = 'This room is no longer available for the selected dates.';
    } else {
        $uploadDir = __DIR__ . '/uploads';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        if (!empty($_FILES['payment_proof']['name'])) {
            $tempFile = $_FILES['payment_proof']['tmp_name'];
            $fileName = time() . '_' . basename($_FILES['payment_proof']['name']);
            $target = $uploadDir . '/' . $fileName;
            if (move_uploaded_file($tempFile, $target)) {
                $paymentProofPath = 'uploads/' . $fileName;
            }
        }

        $nightCount = nightsBetween($_POST['checkin'] ?? $checkin, $_POST['checkout'] ?? $checkout);
        $totalAmount = (float) $room['price_per_night'] * $nightCount;

        $bookingCode = generateBookingCode();
        $statement = db()->prepare('INSERT INTO bookings (booking_code, user_id, room_id, hotel_id, check_in, check_out, guest_count, total_amount, status, payment_proof, special_request) VALUES (:booking_code, :user_id, :room_id, :hotel_id, :check_in, :check_out, :guest_count, :total_amount, :status, :payment_proof, :special_request)');
        $statement->execute([
            ':booking_code' => $bookingCode,
            ':user_id' => (int) $currentUser['id'],
            ':room_id' => $roomId,
            ':hotel_id' => $hotelId,
            ':check_in' => $_POST['checkin'] ?? $checkin,
            ':check_out' => $_POST['checkout'] ?? $checkout,
            ':guest_count' => max(1, (int) ($_POST['guests'] ?? $guests)),
            ':total_amount' => $totalAmount,
            ':status' => 'pending',
            ':payment_proof' => $paymentProofPath,
            ':special_request' => trim((string) ($_POST['special_request'] ?? '')),
        ]);

        $bookingId = (int) db()->lastInsertId();
        $paymentStatement = db()->prepare('INSERT INTO payments (booking_id, method, amount, proof, status) VALUES (:booking_id, :method, :amount, :proof, :status)');
        $paymentStatement->execute([
            ':booking_id' => $bookingId,
            ':method' => 'manual',
            ':amount' => $totalAmount,
            ':proof' => $paymentProofPath,
            ':status' => 'pending',
        ]);

        addNotification((int) $currentUser['id'], 'Your booking ' . $bookingCode . ' has been received and is pending verification.', $bookingId);
        $success = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book room | <?= APP_NAME ?></title>
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
                <a href="profile.php">Profile</a>
                <a href="logout.php" class="btn btn-secondary">Logout</a>
            </nav>
        </div>
    </header>

    <main class="container page-head">
        <div class="booking-hero-box">
            <div>
                <span class="eyebrow">Private reservation</span>
                <h1>Book <?= htmlspecialchars($room['name']) ?></h1>
                <p>Reserve your stay at <?= htmlspecialchars($hotel['name']) ?>.</p>
            </div>
            <div class="booking-badge">
                <span>Estimated total</span>
                <strong><?= formatMoney((float) $room['price_per_night'] * nightsBetween($checkin, $checkout)) ?></strong>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">Booking created successfully! Your reservation #<?= (int) $bookingId ?> is pending verification.</div>
        <?php endif; ?>

        <div class="form-panel">
            <div class="booking-layout">
                <div class="booking-form-wrap">
                    <form method="post" enctype="multipart/form-data" class="form-grid">
                        <div class="field">
                            <label for="guest_name">Guest name</label>
                            <input type="text" id="guest_name" name="guest_name" value="<?= htmlspecialchars($_POST['guest_name'] ?? $currentUser['name']) ?>" required>
                        </div>
                        <div class="field">
                            <label for="guest_email">Guest email</label>
                            <input type="email" id="guest_email" name="guest_email" value="<?= htmlspecialchars($_POST['guest_email'] ?? $currentUser['email']) ?>" required>
                        </div>
                        <div class="field">
                            <label for="guest_phone">Guest phone</label>
                            <input type="tel" id="guest_phone" name="guest_phone" value="<?= htmlspecialchars($_POST['guest_phone'] ?? $currentUser['phone']) ?>" required>
                        </div>
                        <div class="field">
                            <label for="guests">Guests</label>
                            <input type="number" id="guests" name="guests" min="1" max="10" value="<?= (int) ($_POST['guests'] ?? $guests) ?>" required>
                        </div>
                        <div class="field">
                            <label for="checkin">Check-in</label>
                            <input type="date" id="checkin" name="checkin" value="<?= htmlspecialchars($_POST['checkin'] ?? $checkin) ?>" required>
                        </div>
                        <div class="field">
                            <label for="checkout">Check-out</label>
                            <input type="date" id="checkout" name="checkout" value="<?= htmlspecialchars($_POST['checkout'] ?? $checkout) ?>" required>
                        </div>
                        <div class="field field-full">
                            <label for="special_request">Special request</label>
                            <textarea id="special_request" name="special_request" placeholder="Optional request for your stay..."><?= htmlspecialchars($_POST['special_request'] ?? '') ?></textarea>
                        </div>
                        <div class="field field-full">
                            <label for="payment_proof">Payment proof upload</label>
                            <input type="file" id="payment_proof" name="payment_proof" accept="image/*,.pdf">
                        </div>
                        <div class="field-full">
                            <button class="btn btn-primary" type="submit">Complete booking</button>
                        </div>
                    </form>
                </div>

                <aside class="booking-summary booking-side-panel">
                    <div class="summary-row"><span>Hotel</span><strong><?= htmlspecialchars($hotel['name']) ?></strong></div>
                    <div class="summary-row"><span>Room</span><strong><?= htmlspecialchars($room['name']) ?></strong></div>
                    <div class="summary-row"><span>Price per night</span><strong><?= formatMoney((float) $room['price_per_night']) ?></strong></div>
                    <div class="summary-row"><span>Night count</span><strong><?= nightsBetween($checkin, $checkout) ?></strong></div>
                    <div class="summary-row"><span>Total</span><strong><?= formatMoney((float) $room['price_per_night'] * nightsBetween($checkin, $checkout)) ?></strong></div>
                    <div class="summary-row"><span>Check-in</span><strong><?= htmlspecialchars($checkin) ?></strong></div>
                    <div class="summary-row"><span>Check-out</span><strong><?= htmlspecialchars($checkout) ?></strong></div>
                </aside>
            </div>
        </div>
    </main>
</body>
</html>
