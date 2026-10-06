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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrfToken();
    if (isset($_POST['delete_id'])) {
        requireRole(['admin']);
        $statement = $db->prepare('DELETE FROM hotels WHERE id = :id');
        $statement->execute([':id' => (int) $_POST['delete_id']]);
    } else {
        $hotelId = isset($_POST['hotel_id']) && $_POST['hotel_id'] !== '' ? (int) $_POST['hotel_id'] : null;
        if ($hotelAdmin) {
            $hotelId = $managedHotelId;
        }
        $name = trim($_POST['name'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $rating = (float) ($_POST['rating'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        $image = trim($_POST['image'] ?? '');

        if ($name !== '' && $city !== '' && (!$hotelAdmin || $hotelId === $managedHotelId)) {
            if ($hotelId) {
                $statement = $db->prepare('UPDATE hotels SET name = :name, city = :city, address = :address, rating = :rating, description = :description, image = :image WHERE id = :id');
                $statement->execute([
                    ':name' => $name,
                    ':city' => $city,
                    ':address' => $address,
                    ':rating' => $rating,
                    ':description' => $description,
                    ':image' => $image,
                    ':id' => $hotelId,
                ]);
            } else {
                $statement = $db->prepare('INSERT INTO hotels (name, city, address, rating, description, image) VALUES (:name, :city, :address, :rating, :description, :image)');
                $statement->execute([
                    ':name' => $name,
                    ':city' => $city,
                    ':address' => $address,
                    ':rating' => $rating,
                    ':description' => $description,
                    ':image' => $image,
                ]);
            }
        }
    }

    redirect('admin_hotels.php');
}

$hotels = $db->prepare('SELECT * FROM hotels' . ($hotelAdmin ? ' WHERE id = :hotel_id' : '') . ' ORDER BY created_at DESC');
if ($hotelAdmin) {
    $hotels->execute([':hotel_id' => $managedHotelId]);
} else {
    $hotels->execute();
}
$hotelToEdit = null;
if (isset($_GET['edit'])) {
    $editSql = 'SELECT * FROM hotels WHERE id = :id';
    if ($hotelAdmin) {
        $editSql .= ' AND id = :hotel_id';
    }
    $edit = $db->prepare($editSql);
    $editParams = [':id' => (int) $_GET['edit']];
    if ($hotelAdmin) {
        $editParams[':hotel_id'] = $managedHotelId;
    }
    $edit->execute($editParams);
    $hotelToEdit = $edit->fetch() ?: null;
} elseif ($hotelAdmin) {
    $assignedHotel = $db->prepare('SELECT * FROM hotels WHERE id = :hotel_id');
    $assignedHotel->execute([':hotel_id' => $managedHotelId]);
    $hotelToEdit = $assignedHotel->fetch() ?: null;
}
$currentUser = currentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Hotels | <?= APP_NAME ?></title>
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
        <h1>Hotel management</h1>

        <div class="form-panel">
            <h2 style="margin-top:0;"><?= $hotelToEdit ? 'Update hotel' : 'Add hotel' ?></h2>
            <form method="post" class="form-grid">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
                <input type="hidden" name="hotel_id" value="<?= (int) ($hotelToEdit['id'] ?? 0) ?>">
                <div class="field">
                    <label for="name">Hotel name</label>
                    <input type="text" id="name" name="name" value="<?= htmlspecialchars($hotelToEdit['name'] ?? '') ?>" required>
                </div>
                <div class="field">
                    <label for="city">City</label>
                    <input type="text" id="city" name="city" value="<?= htmlspecialchars($hotelToEdit['city'] ?? '') ?>" required>
                </div>
                <div class="field field-full">
                    <label for="address">Address</label>
                    <input type="text" id="address" name="address" value="<?= htmlspecialchars($hotelToEdit['address'] ?? '') ?>">
                </div>
                <div class="field">
                    <label for="rating">Rating</label>
                    <input type="number" id="rating" name="rating" step="0.1" min="0" max="5" value="<?= htmlspecialchars((string) ($hotelToEdit['rating'] ?? '4.8')) ?>">
                </div>
                <div class="field">
                    <label for="image">Image URL</label>
                    <input type="url" id="image" name="image" placeholder="https://..." value="<?= htmlspecialchars($hotelToEdit['image'] ?? '') ?>">
                </div>
                <div class="field field-full">
                    <label for="description">Description</label>
                    <textarea id="description" name="description"><?= htmlspecialchars($hotelToEdit['description'] ?? '') ?></textarea>
                </div>
                <div class="field-full">
                    <button class="btn btn-primary" type="submit"><?= $hotelToEdit ? 'Update hotel' : 'Save hotel' ?></button>
                    <?php if ($hotelToEdit): ?><a href="admin_hotels.php" class="btn btn-secondary">Cancel</a><?php endif; ?>
                </div>
            </form>
        </div>

        <div class="page-head" style="padding-top: 24px;">
            <h2>Hotel list</h2>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>City</th>
                        <th>Rating</th>
                        <th>Description</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($hotel = $hotels->fetch()): ?>
                        <tr>
                            <td><?= htmlspecialchars($hotel['name']) ?></td>
                            <td><?= htmlspecialchars($hotel['city']) ?></td>
                            <td><?= number_format((float) $hotel['rating'], 1) ?></td>
                            <td><?= htmlspecialchars($hotel['description'] ?? '') ?></td>
                            <td>
                                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                                    <a class="btn btn-secondary" href="admin_hotels.php?edit=<?= (int) $hotel['id'] ?>">Edit</a>
                                    <?php if (!$hotelAdmin): ?>
                                        <form method="post" onsubmit="return confirm('Delete this hotel and its related rooms and bookings?');">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
                                            <input type="hidden" name="delete_id" value="<?= (int) $hotel['id'] ?>">
                                            <button type="submit" class="btn btn-warning">Delete</button>
                                        </form>
                                    <?php endif; ?>
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
