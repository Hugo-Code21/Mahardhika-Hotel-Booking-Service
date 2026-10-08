<?php
require __DIR__ . '/includes/bootstrap.php';

$db = db();
$featuredHotels = $db->query('SELECT * FROM hotels ORDER BY rating DESC LIMIT 3');
$checkin = $_GET['checkin'] ?? date('Y-m-d', strtotime('+2 days'));
$checkout = $_GET['checkout'] ?? date('Y-m-d', strtotime('+5 days'));
$guests = $_GET['guests'] ?? 2;
$city = trim((string) ($_GET['city'] ?? 'Bandung'));
$currentUser = currentUser();
$isStaffUser = $currentUser && in_array($currentUser['role'], ['admin', 'hotel_head_admin'], true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?></title>
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
                <?php if ($isStaffUser): ?>
                    <a href="admin.php">Dashboard</a>
                <?php endif; ?>
                <?php if ($currentUser): ?>
                    <a href="profile.php">Hello, <?= htmlspecialchars($currentUser['name']) ?></a>
                    <a href="logout.php" class="btn btn-secondary">Logout</a>
                <?php else: ?>
                    <a href="login.php" class="btn btn-secondary">Login</a>
                    <a href="register.php" class="btn btn-primary">Register</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>

    <main>
        <section class="hero">
            <div class="container hero-grid">
                <div class="hero-copy">
                    <div class="eyebrow">Boutique stays • curated</div>
                    <h1>Slow mornings, beautiful rooms.</h1>
                    <p>Discover beautifully designed stays for city breaks, quiet escapes, and memorable getaways — with effortless booking and a calmer guest experience.</p>
                    <div class="hero-actions">
                        <a href="hotels.php" class="btn btn-primary">Explore stays</a>
                        <?php if ($isStaffUser): ?>
                            <a href="admin.php" class="btn btn-secondary">Hotel dashboard</a>
                        <?php endif; ?>
                    </div>
                    <div class="stats">
                        <div class="stat">
                            <strong>250+</strong>
                            <span>Premium rooms</span>
                        </div>
                        <div class="stat">
                            <strong>4.8/5</strong>
                            <span>Average rating</span>
                        </div>
                        <div class="stat">
                            <strong>18k</strong>
                            <span>Happy guests</span>
                        </div>
                        <div class="stat">
                            <strong>24/7</strong>
                            <span>Support</span>
                        </div>
                    </div>
                </div>

                <div class="hero-card">
                    <div class="booking-shell">
                        <h3>Find your next stay</h3>
                        <form action="hotels.php" method="get" class="search-form">
                            <div class="field field-full">
                                <label for="city">Destination</label>
                                <input type="text" id="city" name="city" value="<?= htmlspecialchars($city) ?>" placeholder="Search hotel city">
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
                                <label for="guests">Guests</label>
                                <select id="guests" name="guests">
                                    <?php for ($i = 1; $i <= 6; $i++): ?>
                                        <option value="<?= $i ?>" <?= $i == (int) $guests ? 'selected' : '' ?>><?= $i ?> guest<?= $i > 1 ? 's' : '' ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary field-full">Search availability</button>
                        </form>
                    </div>
                </div>
            </div>
        </section>

        <section class="section">
            <div class="container">
                <div class="section-title">
                    <h2>Signature escapes</h2>
                    <p>Thoughtfully chosen stays for slower, richer travel.</p>
                </div>
                <div class="destination-strip">
                    <a class="destination-tag" href="hotels.php?city=Bandung">Bandung</a>
                    <a class="destination-tag" href="hotels.php?city=Bali">Bali</a>
                    <a class="destination-tag" href="hotels.php?city=Jakarta">Jakarta</a>
                    <a class="destination-tag" href="hotels.php?city=Yogyakarta">Yogyakarta</a>
                    <a class="destination-tag" href="hotels.php?city=Surabaya">Surabaya</a>
                    <a class="destination-tag" href="hotels.php?city=Semarang">Semarang</a>
                </div>
                <div class="grid">
                    <?php while ($hotel = $featuredHotels->fetch()): ?>
                        <article class="card featured-card">
                            <img src="<?= htmlspecialchars($hotel['image']) ?>" alt="<?= htmlspecialchars($hotel['name']) ?>">
                            <div class="card-body">
                                <h3><?= htmlspecialchars($hotel['name']) ?></h3>
                                <div class="meta">
                                    <span><?= htmlspecialchars($hotel['city']) ?></span>
                                    <span>⭐ <?= number_format((float) $hotel['rating'], 1) ?></span>
                                </div>
                                <p><?= htmlspecialchars($hotel['description']) ?></p>
                                <div class="price">
                                    <span>from</span>
                                    <strong><?= formatMoney(450000) ?></strong>
                                </div>
                            </div>
                        </article>
                    <?php endwhile; ?>
                </div>
            </div>
        </section>

        <section class="section" style="background: rgba(14,116,144,0.02);">
            <div class="container">
                <div class="section-title">
                    <h2>How it feels</h2>
                    <p>Streamlined from discovery to check-in.</p>
                </div>
                <div class="grid">
                    <div class="card info-card">
                        <div class="card-body">
                            <div class="info-icon">01</div>
                            <h3>Search</h3>
                            <p>Pick your destination, dates, and guest count to see what is available right now.</p>
                        </div>
                    </div>
                    <div class="card info-card">
                        <div class="card-body">
                            <div class="info-icon">02</div>
                            <h3>Choose room</h3>
                            <p>Compare room types, pricing, amenities, and capacity before booking.</p>
                        </div>
                    </div>
                    <div class="card info-card">
                        <div class="card-body">
                            <div class="info-icon">03</div>
                            <h3>Confirm</h3>
                            <p>Complete the booking and share payment proof for secure reservation management.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="section">
            <div class="container">
                <div class="section-title">
                    <h2>Why travelers choose StayEase</h2>
                    <p>Comfort, clarity, and a smooth reservation experience.</p>
                </div>
                <div class="benefit-grid">
                    <div class="benefit-card">
                        <div class="benefit-icon">✓</div>
                        <h3>Verified stays</h3>
                        <p>Handpicked properties and transparent room details from trusted hotel partners.</p>
                    </div>
                    <div class="benefit-card">
                        <div class="benefit-icon">⏱</div>
                        <h3>Fast booking</h3>
                        <p>Search, select, and confirm in minutes without messy back-and-forth communication.</p>
                    </div>
                    <div class="benefit-card">
                        <div class="benefit-icon">💬</div>
                        <h3>Support anytime</h3>
                        <p>Customer and staff support for check-ins, payments, and reservation updates.</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="section testimonial-section">
            <div class="container">
                <div class="section-title">
                    <h2>Guest feedback</h2>
                    <p>Real experiences from travelers who booked through StayEase.</p>
                </div>
                <div class="testimonial-grid">
                    <article class="testimonial-card">
                        <div class="stars">★★★★★</div>
                        <p>“The booking process was super smooth and the room was exactly as advertised. I loved the quick admin confirmation.”</p>
                        <div class="testimonial-user">
                            <strong>Rina S.</strong>
                            <span>Bandung</span>
                        </div>
                    </article>
                    <article class="testimonial-card">
                        <div class="stars">★★★★★</div>
                        <p>“Great location, clean rooms, and very simple payment process. The interface is easy even for first-time users.”</p>
                        <div class="testimonial-user">
                            <strong>Arif H.</strong>
                            <span>Bali</span>
                        </div>
                    </article>
                    <article class="testimonial-card">
                        <div class="stars">★★★★★</div>
                        <p>“We used the service for a family trip and everything from room selection to check-in was organized and stress-free.”</p>
                        <div class="testimonial-user">
                            <strong>Mei L.</strong>
                            <span>Bogor</span>
                        </div>
                    </article>
                </div>
            </div>
        </section>
    </main>

    <footer class="site-footer">
        <div class="container footer-grid">
            <div>
                <div class="brand" style="margin-bottom: 12px;">
                    <span class="brand-mark">S</span>
                    <span>StayEase</span>
                </div>
                <p>Modern hotel booking for guests, hotel teams, and reservation operations.</p>
            </div>
            <div>
                <h4>Company</h4>
                <ul>
                    <li><a href="index.php">Home</a></li>
                    <li><a href="hotels.php">Hotels</a></li>
                    <?php if ($isStaffUser): ?>
                        <li><a href="admin.php">Admin dashboard</a></li>
                    <?php endif; ?>
                </ul>
            </div>
            <div>
                <h4>Support</h4>
                <ul>
                    <li><a href="login.php">Login</a></li>
                    <li><a href="register.php">Register</a></li>
                    <li><a href="reviews.php?hotel_id=1">Reviews</a></li>
                </ul>
            </div>
            <div>
                <h4>Contact</h4>
                <ul>
                    <li>support@stayease.com</li>
                    <li>+62 21 555 1234</li>
                    <li>Bandung, Indonesia</li>
                </ul>
            </div>
        </div>
        <div class="container footer-bottom">
            <p>© 2026 StayEase. Built for hotel booking, guest management, and reservation operations.</p>
        </div>
    </footer>
</body>
</html>
