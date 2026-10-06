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
$roomToEdit = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrfToken();
    if (isset($_POST['delete_id'])) {
        $deleteSql = 'DELETE FROM rooms WHERE id = :id';
        $params = [':id' => (int) $_POST['delete_id']];
        if ($hotelAdmin) {
            $deleteSql .= ' AND hotel_id = :hotel_id';
            $params[':hotel_id'] = $managedHotelId;
        }
        $statement = $db->prepare($deleteSql);
        $statement->execute($params);
    } else {
        $roomId = isset($_POST['room_id']) && $_POST['room_id'] !== '' ? (int) $_POST['room_id'] : null;
        $hotelId = (int) ($_POST['hotel_id'] ?? 0);
        if ($hotelAdmin) {
            $hotelId = $managedHotelId;
        }
        $name = trim($_POST['name'] ?? '');
        $price = (float) ($_POST['price_per_night'] ?? 0);
        $maxGuests = (int) ($_POST['max_guests'] ?? 2);
        $bedType = trim($_POST['bed_type'] ?? '');
        $facilities = trim($_POST['facilities'] ?? '');
        $image = trim($_POST['image'] ?? '');

        if ($hotelId > 0 && $name !== '' && (!$hotelAdmin || $hotelId === $managedHotelId)) {
            if ($roomId) {
                $updateSql = 'UPDATE rooms SET hotel_id = :hotel_id, name = :name, price_per_night = :price, max_guests = :max_guests, bed_type = :bed_type, facilities = :facilities, image = :image WHERE id = :id';
                if ($hotelAdmin) {
                    $updateSql .= ' AND hotel_id = :managed_hotel_id';
                }
                $statement = $db->prepare($updateSql);
                $params = [
                    ':hotel_id' => $hotelId,
                    ':name' => $name,
                    ':price' => $price,
                    ':max_guests' => $maxGuests,
                    ':bed_type' => $bedType,
                    ':facilities' => $facilities,
                    ':image' => $image,
                    ':id' => $roomId,
                ];
                if ($hotelAdmin) {
                    $params[':managed_hotel_id'] = $managedHotelId;
                }
                $statement->execute($params);
            } else {
                $statement = $db->prepare('INSERT INTO rooms (hotel_id, name, price_per_night, max_guests, bed_type, facilities, image) VALUES (:hotel_id, :name, :price, :max_guests, :bed_type, :facilities, :image)');
                $statement->execute([
                    ':hotel_id' => $hotelId,
                    ':name' => $name,
                    ':price' => $price,
                    ':max_guests' => $maxGuests,
                    ':bed_type' => $bedType,
                    ':facilities' => $facilities,
                    ':image' => $image,
                ]);
            }
        }
    }

    redirect('admin_rooms.php');
}

$roomsSql = 'SELECT r.*, h.name AS hotel_name FROM rooms r JOIN hotels h ON h.id = r.hotel_id';
if ($hotelAdmin) {
    $roomsSql .= ' WHERE r.hotel_id = :hotel_id';
}
$roomsSql .= ' ORDER BY h.name, r.name';
$rooms = $db->prepare($roomsSql);
if ($hotelAdmin) {
    $rooms->execute([':hotel_id' => $managedHotelId]);
} else {
    $rooms->execute();
}
$hotels = $db->prepare('SELECT * FROM hotels' . ($hotelAdmin ? ' WHERE id = :hotel_id' : '') . ' ORDER BY name');
if ($hotelAdmin) {
    $hotels->execute([':hotel_id' => $managedHotelId]);
} else {
    $hotels->execute();
}
if (isset($_GET['edit'])) {
    $editSql = 'SELECT * FROM rooms WHERE id = :id';
    if ($hotelAdmin) {
        $editSql .= ' AND hotel_id = :hotel_id';
    }
    $edit = $db->prepare($editSql);
    $editParams = [':id' => (int) $_GET['edit']];
    if ($hotelAdmin) {
        $editParams[':hotel_id'] = $managedHotelId;
    }
    $edit->execute($editParams);
    $roomToEdit = $edit->fetch() ?: null;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Rooms | <?= APP_NAME ?></title>
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
        <h1>Room management</h1>

        <div class="form-panel">
            <h2 style="margin-top:0;"><?= $roomToEdit ? 'Update room' : 'Add room' ?></h2>
            <form method="post" class="form-grid">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
                <input type="hidden" name="room_id" value="<?= (int) ($roomToEdit['id'] ?? 0) ?>">
                <div class="field">
                    <label for="hotel_id">Hotel</label>
                    <select id="hotel_id" name="hotel_id" required <?= $hotelAdmin ? 'disabled' : '' ?>>
                        <?php while ($hotel = $hotels->fetch()): ?>
                            <option value="<?= (int) $hotel['id'] ?>" <?= (int) ($roomToEdit['hotel_id'] ?? $managedHotelId ?? 0) === (int) $hotel['id'] ? 'selected' : '' ?>><?= htmlspecialchars($hotel['name']) ?></option>
                        <?php endwhile; ?>
                    </select>
                    <?php if ($hotelAdmin): ?><input type="hidden" name="hotel_id" value="<?= $managedHotelId ?>"><?php endif; ?>
                </div>
                <div class="field">
                    <label for="name">Room name</label>
                    <input type="text" id="name" name="name" value="<?= htmlspecialchars($roomToEdit['name'] ?? '') ?>" required>
                </div>
                <div class="field">
                    <label for="price_per_night">Price per night</label>
                    <input type="number" id="price_per_night" name="price_per_night" min="0" step="1000" value="<?= htmlspecialchars((string) ($roomToEdit['price_per_night'] ?? '')) ?>" required>
                </div>
                <div class="field">
                    <label for="max_guests">Max guests</label>
                    <input type="number" id="max_guests" name="max_guests" min="1" value="<?= (int) ($roomToEdit['max_guests'] ?? 2) ?>" required>
                </div>
                <div class="field">
                    <label for="bed_type">Bed type</label>
                    <input type="text" id="bed_type" name="bed_type" placeholder="1 King Bed" value="<?= htmlspecialchars($roomToEdit['bed_type'] ?? '') ?>">
                </div>
                <div class="field">
                    <label for="image">Image URL</label>
                    <input type="url" id="image" name="image" placeholder="https://..." value="<?= htmlspecialchars($roomToEdit['image'] ?? '') ?>">
                </div>
                <div class="field field-full">
                    <label for="facilities">Facilities</label>
                    <input type="text" id="facilities" name="facilities" placeholder="WiFi, AC, TV, Breakfast" value="<?= htmlspecialchars($roomToEdit['facilities'] ?? '') ?>">
                </div>
                <div class="field-full">
                    <button type="submit" class="btn btn-primary"><?= $roomToEdit ? 'Update room' : 'Save room' ?></button>
                    <?php if ($roomToEdit): ?><a href="admin_rooms.php" class="btn btn-secondary">Cancel</a><?php endif; ?>
                </div>
            </form>
        </div>

        <div class="page-head" style="padding-top: 24px;">
            <h2>Room list</h2>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Hotel</th>
                        <th>Room</th>
                        <th>Price</th>
                        <th>Guests</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($room = $rooms->fetch()): ?>
                        <tr>
                            <td><?= htmlspecialchars($room['hotel_name']) ?></td>
                            <td><?= htmlspecialchars($room['name']) ?></td>
                            <td><?= formatMoney((float) $room['price_per_night']) ?></td>
                            <td><?= (int) $room['max_guests'] ?></td>
                            <td>
                                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                                    <a href="admin_rooms.php?edit=<?= (int) $room['id'] ?>" class="btn btn-secondary">Edit</a>
                                    <form method="post" onsubmit="return confirm('Delete this room?');">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
                                        <input type="hidden" name="delete_id" value="<?= (int) $room['id'] ?>">
                                        <button type="submit" class="btn btn-warning">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </main>
</body>
</html>
