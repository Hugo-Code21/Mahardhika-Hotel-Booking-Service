<?php
function loadEnvironment(): void
{
    $environmentFile = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env';
    if (!is_file($environmentFile) || !is_readable($environmentFile)) {
        return;
    }

    $lines = file($environmentFile, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
            continue;
        }

        [$name, $value] = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        if (!preg_match('/^[A-Z][A-Z0-9_]*$/', $name)) {
            continue;
        }

        if (getenv($name) !== false) {
            continue;
        }

        if (strlen($value) >= 2 && (($value[0] === '"' && substr($value, -1) === '"') || ($value[0] === "'" && substr($value, -1) === "'"))) {
            $value = substr($value, 1, -1);
        }

        $_ENV[$name] = $value;
    }
}

function environmentValue(string $name, string $default): string
{
    $value = getenv($name);
    if ($value !== false) {
        return $value;
    }

    return isset($_ENV[$name]) ? (string) $_ENV[$name] : $default;
}

loadEnvironment();

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);
header_remove('X-Powered-By');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
ob_start();

set_exception_handler('handleUncaughtException');
register_shutdown_function('handleFatalError');

ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.use_trans_sid', '0');
$secureCookieSetting = strtolower(environmentValue('SESSION_COOKIE_SECURE', 'auto'));
$secureCookie = $secureCookieSetting === 'auto'
    ? (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
    : filter_var($secureCookieSetting, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $secureCookie ?? true,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

const APP_NAME = 'StayEase — Hotel Booking Service';

function handleUncaughtException(Throwable $exception): void
{
    error_log('Unhandled application exception: ' . get_class($exception));

    renderGenericServerError();
    exit(1);
}

function handleFatalError(): void
{
    $error = error_get_last();
    $fatalErrorTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];

    if ($error === null || !in_array($error['type'], $fatalErrorTypes, true)) {
        return;
    }

    error_log(sprintf('Fatal PHP error (type %d).', $error['type']));

    renderGenericServerError();
}

function renderGenericServerError(): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=UTF-8');
        header('Cache-Control: no-store');
        header('X-Content-Type-Options: nosniff');
    }

    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Service unavailable</title></head>'
        . '<body><h1>Something went wrong</h1><p>Please try again later.</p></body></html>';
}

function db(): PDO
{
    static $database = null;

    if ($database === null) {
        $host = environmentValue('DB_HOST', '127.0.0.1');
        $port = (int) environmentValue('DB_PORT', '3306');
        $name = environmentValue('DB_NAME', 'mahardhika_hotel_booking');
        $username = environmentValue('DB_USER', 'root');
        $password = environmentValue('DB_PASSWORD', '');

        if (!preg_match('/^[a-zA-Z0-9_]+$/', $name) || $port < 1 || $port > 65535) {
            throw new RuntimeException('Invalid MySQL connection configuration.');
        }

        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $name);
        $database = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    return $database;
}

function formatMoney(float $amount): string
{
    return 'Rp ' . number_format($amount, 0, ',', '.');
}

function currentUser(): ?array
{
    if (!isset($_SESSION['user_id'])) {
        return null;
    }

    $statement = db()->prepare('SELECT * FROM users WHERE id = :id');
    $statement->execute([':id' => (int) $_SESSION['user_id']]);
    $user = $statement->fetch();

    return $user ?: null;
}

function requireAuth(): void
{
    if (!currentUser()) {
        header('Location: /login.php');
        exit;
    }
}

function requireRole(array $roles): void
{
    $user = currentUser();
    if (!$user || !in_array($user['role'], $roles, true)) {
        if (isset($_SERVER['HTTP_REFERER'])) {
            $_SESSION['redirect_after_login'] = $_SERVER['HTTP_REFERER'];
        }
        redirect('login.php');
    }
}

function currentManagedHotelId(): ?int
{
    $user = currentUser();
    if (!$user || $user['role'] !== 'hotel_head_admin' || empty($user['hotel_id'])) {
        return null;
    }

    return (int) $user['hotel_id'];
}

function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function csrfToken(): string
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function requireValidCsrfToken(): void
{
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!is_string($submittedToken) || !hash_equals(csrfToken(), $submittedToken)) {
        http_response_code(400);
        exit('Invalid request.');
    }
}

function ensureDatabase(): void
{
    $db = db();

    $db->exec('CREATE TABLE IF NOT EXISTS users (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(160) NOT NULL,
        email VARCHAR(254) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        phone VARCHAR(40) NULL,
        role ENUM("admin", "customer", "hotel_head_admin") NOT NULL DEFAULT "customer",
        hotel_id BIGINT UNSIGNED NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

    $db->exec('CREATE TABLE IF NOT EXISTS hotels (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(190) NOT NULL,
        city VARCHAR(120) NOT NULL,
        address TEXT NULL,
        rating DECIMAL(2,1) NOT NULL DEFAULT 0,
        description TEXT NULL,
        image TEXT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

    $db->exec('CREATE TABLE IF NOT EXISTS rooms (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        hotel_id BIGINT UNSIGNED NOT NULL,
        name VARCHAR(190) NOT NULL,
        price_per_night DECIMAL(12,2) NOT NULL,
        max_guests INT UNSIGNED NOT NULL DEFAULT 2,
        bed_type VARCHAR(120) NULL,
        facilities TEXT NULL,
        image TEXT NULL,
        room_number VARCHAR(40) NULL,
        room_type VARCHAR(120) NULL,
        status VARCHAR(32) NOT NULL DEFAULT "available",
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (hotel_id) REFERENCES hotels(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

    $db->exec('CREATE TABLE IF NOT EXISTS room_types (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        hotel_id BIGINT UNSIGNED NOT NULL,
        name VARCHAR(190) NOT NULL,
        description TEXT NULL,
        price_per_night DECIMAL(12,2) NOT NULL,
        capacity INT UNSIGNED NOT NULL DEFAULT 2,
        bed_type VARCHAR(120) NULL,
        size VARCHAR(40) NULL,
        facilities TEXT NULL,
        image TEXT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (hotel_id) REFERENCES hotels(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

    $db->exec('CREATE TABLE IF NOT EXISTS bookings (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        booking_code VARCHAR(80) NULL UNIQUE,
        user_id BIGINT UNSIGNED NOT NULL,
        room_id BIGINT UNSIGNED NOT NULL,
        hotel_id BIGINT UNSIGNED NOT NULL,
        check_in DATE NOT NULL,
        check_out DATE NOT NULL,
        guest_count INT UNSIGNED NOT NULL DEFAULT 2,
        total_amount DECIMAL(12,2) NOT NULL,
        status VARCHAR(32) NOT NULL DEFAULT "pending",
        special_request TEXT NULL,
        payment_proof TEXT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE,
        FOREIGN KEY (hotel_id) REFERENCES hotels(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

    $db->exec('CREATE TABLE IF NOT EXISTS payments (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        booking_id BIGINT UNSIGNED NOT NULL,
        method VARCHAR(40) NOT NULL DEFAULT "manual",
        amount DECIMAL(12,2) NOT NULL,
        proof TEXT NULL,
        status VARCHAR(32) NOT NULL DEFAULT "pending",
        paid_at DATETIME NULL,
        verified_by BIGINT UNSIGNED NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
        FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

    $db->exec('CREATE TABLE IF NOT EXISTS notifications (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        user_id BIGINT UNSIGNED NOT NULL,
        booking_id BIGINT UNSIGNED NULL,
        message TEXT NOT NULL,
        is_read TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

    $db->exec('CREATE TABLE IF NOT EXISTS reviews (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        hotel_id BIGINT UNSIGNED NOT NULL,
        user_id BIGINT UNSIGNED NOT NULL,
        rating TINYINT UNSIGNED NOT NULL,
        comment TEXT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (hotel_id) REFERENCES hotels(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

    addMissingColumns();

    $hotelCount = (int) $db->query('SELECT COUNT(*) FROM hotels')->fetchColumn();
    if ($hotelCount === 0 && !defined('APP_SKIP_SEED')) {
        seedDatabase();
    }

    if (!defined('APP_SKIP_SEED')) {
        ensureJakartaDestination();
    }
    $db->exec("UPDATE users SET hotel_id = (SELECT MIN(id) FROM hotels) WHERE role = 'hotel_head_admin' AND hotel_id IS NULL");
    ensureUserHotelRelation();
}

function ensureJakartaDestination(): void
{
    $db = db();
    $statement = $db->prepare('SELECT id FROM hotels WHERE city = :city LIMIT 1');
    $statement->execute([':city' => 'Jakarta']);
    if ($statement->fetch()) {
        return;
    }

    $db->prepare('INSERT INTO hotels (name, city, address, rating, description, image) VALUES (:name, :city, :address, :rating, :description, :image)')
        ->execute([
            ':name' => 'Jakarta Central Hotel',
            ':city' => 'Jakarta',
            ':address' => 'Jl. Sudirman No. 10, Jakarta',
            ':rating' => 4.5,
            ':description' => 'A comfortable city stay close to Jakarta business and dining districts.',
            ':image' => 'https://images.unsplash.com/photo-1566073771259-6a8506099945?auto=format&fit=crop&w=900&q=80',
        ]);
    $hotelId = (int) $db->lastInsertId();
    $roomStatement = $db->prepare('INSERT INTO rooms (hotel_id, name, price_per_night, max_guests, bed_type, facilities, image, room_number, room_type, status) VALUES (:hotel_id, :name, :price, :guests, :bed_type, :facilities, :image, :room_number, :room_type, :status)');
    foreach ([
        ['City Comfort Room', 590000, 2, '1 Queen Bed', 'WiFi, AC, TV, Breakfast', '701', 'City Comfort'],
        ['Executive Suite', 890000, 3, '1 King Bed + Sofa', 'WiFi, AC, TV, Lounge', '702', 'Executive'],
    ] as $room) {
        $roomStatement->execute([
            ':hotel_id' => $hotelId,
            ':name' => $room[0],
            ':price' => $room[1],
            ':guests' => $room[2],
            ':bed_type' => $room[3],
            ':facilities' => $room[4],
            ':image' => 'https://images.unsplash.com/photo-1505693416388-ac5ce068fe85?auto=format&fit=crop&w=900&q=80',
            ':room_number' => $room[5],
            ':room_type' => $room[6],
            ':status' => 'available',
        ]);
    }
}

function addMissingColumns(): void
{
    $db = db();
    $userColumns = $db->query('SHOW COLUMNS FROM users')->fetchAll();
    $columnNames = array_column($userColumns, 'Field');
    if (!in_array('hotel_id', $columnNames, true)) {
        $db->exec('ALTER TABLE users ADD COLUMN hotel_id BIGINT UNSIGNED NULL AFTER role');
    }

    $roleColumn = null;
    foreach ($userColumns as $column) {
        if ($column['Field'] === 'role') {
            $roleColumn = $column;
            break;
        }
    }

    if ($roleColumn === null) {
        throw new RuntimeException('The users table is missing its role column.');
    }

    $expectedRoleType = "enum('admin','customer','hotel_head_admin')";
    if (strtolower((string) $roleColumn['Type']) !== $expectedRoleType) {
        $db->exec("ALTER TABLE users MODIFY role VARCHAR(32) NOT NULL DEFAULT 'customer'");
        $db->exec("UPDATE users SET role = 'hotel_head_admin' WHERE role = 'receptionist'");
        $db->exec("ALTER TABLE users MODIFY role ENUM('admin', 'customer', 'hotel_head_admin') NOT NULL DEFAULT 'customer'");
    }
}

function ensureUserHotelRelation(): void
{
    $statement = db()->prepare("SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND CONSTRAINT_NAME = 'fk_users_hotel'");
    $statement->execute();
    if ((int) $statement->fetchColumn() === 0) {
        db()->exec('ALTER TABLE users ADD CONSTRAINT fk_users_hotel FOREIGN KEY (hotel_id) REFERENCES hotels(id) ON DELETE SET NULL');
    }
}

function generateBookingCode(): string
{
    do {
        $code = 'STAY-' . date('Y') . '-' . str_pad((string) random_int(1000, 9999), 4, '0', STR_PAD_LEFT);
        $exists = db()->prepare('SELECT id FROM bookings WHERE booking_code = :code');
        $exists->execute([':code' => $code]);
    } while ($exists->fetch() !== false);

    return $code;
}

function addNotification(int $userId, string $message, ?int $bookingId = null): void
{
    $statement = db()->prepare('INSERT INTO notifications (user_id, booking_id, message, is_read) VALUES (:user_id, :booking_id, :message, 0)');
    $statement->execute([
        ':user_id' => $userId,
        ':booking_id' => $bookingId,
        ':message' => $message,
    ]);
}

function seedDatabase(): void
{
    $db = db();
    $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);

    $db->prepare("INSERT INTO users (name, email, password, phone, role) VALUES (:name, :email, :password, :phone, :role)")
        ->execute([
            ':name' => 'System Admin',
            ':email' => 'admin@stayease.com',
            ':password' => $adminPassword,
            ':phone' => '081234567890',
            ':role' => 'admin',
        ]);

    $db->exec("INSERT INTO hotels (name, city, address, rating, description, image) VALUES
        ('Grand Mahardhika Hotel', 'Bandung', 'Jl. Asia Afrika No. 18, Bandung', 4.8, 'Modern city hotel with a rooftop lounge and family-friendly amenities.', 'https://images.unsplash.com/photo-1566073771259-6a8506099945?auto=format&fit=crop&w=900&q=80'),
        ('Lakeview Resort', 'Bali', 'Jl. Pantai Saba, Bali', 4.7, 'Ocean-inspired resort with pool access and panoramic views.', 'https://images.unsplash.com/photo-1505693416388-ac5ce068fe85?auto=format&fit=crop&w=900&q=80'),
        ('Puncak Serenity Inn', 'Bogor', 'Jl. Raya Puncak No. 90, Bogor', 4.6, 'Cool mountain retreat for relaxation and romantic escapes.', 'https://images.unsplash.com/photo-1520250497591-112f2f40a3f4?auto=format&fit=crop&w=900&q=80'),
        ('Jakarta Central Hotel', 'Jakarta', 'Jl. Sudirman No. 10, Jakarta', 4.5, 'A comfortable city stay close to Jakarta business and dining districts.', 'https://images.unsplash.com/photo-1566073771259-6a8506099945?auto=format&fit=crop&w=900&q=80')");

    $db->prepare("INSERT INTO users (name, email, password, phone, role, hotel_id) VALUES (:name, :email, :password, :phone, :role, :hotel_id)")
        ->execute([
            ':name' => 'Hotel Head Admin',
            ':email' => 'hoteladmin@stayease.com',
            ':password' => $adminPassword,
            ':phone' => '081234567891',
            ':role' => 'hotel_head_admin',
            ':hotel_id' => 1,
        ]);

    $db->prepare("INSERT INTO users (name, email, password, phone, role) VALUES (:name, :email, :password, :phone, :role)")
        ->execute([
            ':name' => 'Andi Pratama',
            ':email' => 'andi@example.com',
            ':password' => $adminPassword,
            ':phone' => '081234567892',
            ':role' => 'customer',
        ]);

    $db->exec("INSERT INTO rooms (hotel_id, name, price_per_night, max_guests, bed_type, facilities, image, room_number, room_type, status) VALUES
        (1, 'Standard Room', 450000, 2, '1 King Bed', 'WiFi, AC, TV, Breakfast', 'https://images.unsplash.com/photo-1505693416388-ac5ce068fe85?auto=format&fit=crop&w=900&q=80', '101', 'Standard', 'available'),
        (1, 'Deluxe Room', 650000, 3, '1 King Bed + Sofa', 'WiFi, AC, TV, Breakfast, Pool Access', 'https://images.unsplash.com/photo-1522708323590-d24dbb6b0267?auto=format&fit=crop&w=900&q=80', '201', 'Deluxe', 'occupied'),
        (2, 'Garden Villa', 820000, 4, '1 King Bed + Terrace', 'WiFi, AC, Sea View, Breakfast', 'https://images.unsplash.com/photo-1505693416388-ac5ce068fe85?auto=format&fit=crop&w=900&q=80', '301', 'Garden Villa', 'available'),
        (2, 'Ocean Suite', 980000, 4, '1 King Bed + Lounge', 'WiFi, AC, Private Pool, Breakfast', 'https://images.unsplash.com/photo-1494526585095-c41746248156?auto=format&fit=crop&w=900&q=80', '401', 'Suite', 'available'),
        (3, 'Forest Cabin', 520000, 2, '1 Queen Bed', 'WiFi, AC, Balcony, Breakfast', 'https://images.unsplash.com/photo-1502672260266-1c1ef2d93688?auto=format&fit=crop&w=900&q=80', '501', 'Cabin', 'available'),
        (3, 'Mountain Family Room', 760000, 4, '2 Queen Beds', 'WiFi, AC, TV, Breakfast, Restaurant', 'https://images.unsplash.com/photo-1551882547-ff40c63fe5fa?auto=format&fit=crop&w=900&q=80', '601', 'Family', 'available'),
        (4, 'City Comfort Room', 590000, 2, '1 Queen Bed', 'WiFi, AC, TV, Breakfast', 'https://images.unsplash.com/photo-1505693416388-ac5ce068fe85?auto=format&fit=crop&w=900&q=80', '701', 'City Comfort', 'available'),
        (4, 'Executive Suite', 890000, 3, '1 King Bed + Sofa', 'WiFi, AC, TV, Lounge', 'https://images.unsplash.com/photo-1522708323590-d24dbb6b0267?auto=format&fit=crop&w=900&q=80', '702', 'Executive', 'available')");

    $db->exec("INSERT INTO room_types (hotel_id, name, description, price_per_night, capacity, bed_type, size, facilities, image) VALUES
        (1, 'Standard Room', 'Comfortable city stay for short trips and business travel.', 450000, 2, '1 King Bed', '28 m²', 'WiFi, AC, TV, Breakfast', 'https://images.unsplash.com/photo-1505693416388-ac5ce068fe85?auto=format&fit=crop&w=900&q=80'),
        (1, 'Deluxe Room', 'A spacious option with added comfort and premium amenities.', 650000, 3, '1 King Bed + Sofa', '32 m²', 'WiFi, AC, TV, Breakfast, Pool Access', 'https://images.unsplash.com/photo-1522708323590-d24dbb6b0267?auto=format&fit=crop&w=900&q=80'),
        (2, 'Garden Villa', 'Private villa style room with open-air comfort and greenery.', 820000, 4, '1 King Bed + Terrace', '38 m²', 'WiFi, AC, Sea View, Breakfast', 'https://images.unsplash.com/photo-1505693416388-ac5ce068fe85?auto=format&fit=crop&w=900&q=80'),
        (2, 'Ocean Suite', 'Premium suite with lounge feel and resort-level features.', 980000, 4, '1 King Bed + Lounge', '42 m²', 'WiFi, AC, Private Pool, Breakfast', 'https://images.unsplash.com/photo-1494526585095-c41746248156?auto=format&fit=crop&w=900&q=80'),
        (3, 'Forest Cabin', 'Mountain getaway with a calm and cozy cabin feel.', 520000, 2, '1 Queen Bed', '30 m²', 'WiFi, AC, Balcony, Breakfast', 'https://images.unsplash.com/photo-1502672260266-1c1ef2d93688?auto=format&fit=crop&w=900&q=80'),
        (3, 'Mountain Family Room', 'A family-sized room with space for comfort and togetherness.', 760000, 4, '2 Queen Beds', '40 m²', 'WiFi, AC, TV, Breakfast, Restaurant', 'https://images.unsplash.com/photo-1551882547-ff40c63fe5fa?auto=format&fit=crop&w=900&q=80')");

    $db->exec("INSERT INTO bookings (booking_code, user_id, room_id, hotel_id, check_in, check_out, guest_count, total_amount, status, special_request) VALUES
        ('STAY-2026-1001', 3, 1, 1, '2026-10-10', '2026-10-12', 2, 900000, 'confirmed', 'Late arrival'),
        ('STAY-2026-1002', 3, 3, 2, '2026-11-05', '2026-11-08', 2, 2460000, 'pending', 'Early check-in')");

    $db->exec("INSERT INTO payments (booking_id, method, amount, status) VALUES
        (1, 'manual', 900000, 'verified'),
        (2, 'manual', 2460000, 'pending')");
}

function isRoomAvailable(int $roomId, string $checkIn, string $checkOut): bool
{
    $db = db();
    $statement = $db->prepare('SELECT id FROM bookings WHERE room_id = :room_id AND status != :cancelled AND check_in < :check_out AND check_out > :check_in');
    $statement->execute([
        ':room_id' => $roomId,
        ':cancelled' => 'cancelled',
        ':check_in' => $checkIn,
        ':check_out' => $checkOut,
    ]);

    return $statement->fetch() === false;
}

function getHotelById(int $hotelId): ?array
{
    $statement = db()->prepare('SELECT * FROM hotels WHERE id = :id');
    $statement->execute([':id' => $hotelId]);
    $hotel = $statement->fetch();

    return $hotel ?: null;
}

function getRoomById(int $roomId): ?array
{
    $statement = db()->prepare('SELECT * FROM rooms WHERE id = :id');
    $statement->execute([':id' => $roomId]);
    $room = $statement->fetch();

    return $room ?: null;
}

function nightsBetween(string $checkIn, string $checkOut): int
{
    $from = new DateTimeImmutable($checkIn);
    $to = new DateTimeImmutable($checkOut);
    return (int) $to->diff($from)->format('%a');
}

ensureDatabase();
