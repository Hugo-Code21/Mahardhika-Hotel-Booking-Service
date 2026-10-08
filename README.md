# StayEase — Hotel Booking Service

A PHP hotel booking application for searching destinations, choosing rooms, booking stays, uploading payment proof, and managing hotel operations.

## Features

- Customer registration, login, booking history, and profile
- Clickable Bandung, Bali, and Jakarta destination searches
- Room selection, availability checks, and booking
- MySQL/MariaDB storage on the local XAMPP server
- Three account roles stored as a database ENUM: `admin`, `customer`, and `hotel_head_admin`
- Admin CRUD for user accounts and global hotel operations
- Hotel Head Admin access scoped to their assigned hotel, rooms, bookings, and payments
- Generic error responses that do not expose stack traces

## Local MySQL setup

The local `.env` is ignored by Git. Its XAMPP defaults connect to `localhost:3306` as `root` with an empty password. Change `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, and `DB_PASSWORD` there to match your local MySQL configuration. Do not use the empty-password root account outside a local development environment.

1. Start **Apache** and **MySQL** in the XAMPP Control Panel.
2. Import `database.sql` in phpMyAdmin. It creates the `mahardhika_hotel_booking` database.
3. To preserve and convert the existing `database.sqlite` data, run this command once from the project root **before opening the application**:

   ```bash
   php migrate_sqlite_to_mysql.php
   ```

   The migration refuses to run if the MySQL tables already contain records. It keeps existing IDs and password hashes, and maps the old receptionist account to a Hotel Head Admin assigned to the first hotel. Keep a backup of `database.sqlite` until you verify the imported data.
4. Run the application:

   ```bash
   php -S localhost:8000 router.php
   ```

   Then open `http://localhost:8000/`.

If you do not need the SQLite data, skip the migration command; the application creates the MySQL tables and sample data on first run.

## Demo accounts

The seeded local demo accounts all use `admin123`:

- Admin: `admin@stayease.com`
- Hotel Head Admin: `hoteladmin@stayease.com` (assigned to Grand Mahardhika Hotel)
- Customer: `andi@example.com`

Change demo passwords before using non-demo data. Admin accounts manage all user roles. Hotel Head Admin accounts can manage only their assigned hotel and its related operations.

## Project structure

- `index.php` — home page and destination search
- `hotels.php` — destination results
- `rooms.php` / `booking.php` — room selection and booking
- `login.php` / `register.php` — account access
- `admin.php` — hotel operations dashboard
- `admin_customers.php` — Admin-only user account CRUD
- `admin_hotels.php` / `admin_rooms.php` — hotel and room management
- `admin_payments.php` — booking and payment status management
- `includes/bootstrap.php` — MySQL connection, schema setup, and shared helpers
- `database.sql` — creates the local MySQL database
- `migrate_sqlite_to_mysql.php` — one-time CLI-only data importer
- `assets/style.css` — responsive styling and readable typography

stay reading, stay coding!

<!-- if you need any help just dm me : IG sudo.desdev; -->
