<?php
// ============================================================
// Ligao Petcare & Veterinary Clinic
// File: user/dashboard.php
// Purpose: User home dashboard — banner, appointments, announcements
// ============================================================
require_once '../includes/auth.php';
requireLogin();
if (isAdmin()) redirect('../admin/dashboard.php');

$user_id   = (int)$_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// ── Fetch upcoming appointments (next 3) ────────────────────
$appointments = getRows($conn,
    "SELECT a.*, p.name AS pet_name, s.name AS service_name
     FROM appointments a
     LEFT JOIN pets p ON a.pet_id = p.id
     LEFT JOIN services s ON a.service_id = s.id
     WHERE a.user_id = $user_id
       AND a.status IN ('pending','confirmed')
       AND a.appointment_date >= CURDATE()
     ORDER BY a.appointment_date ASC, a.appointment_time ASC
     LIMIT 3"
);

// ── Fetch latest announcements ───────────────────────────────
$announcements = getRows($conn,
    "SELECT * FROM announcements ORDER BY created_at DESC LIMIT 3"
);

// ── Fetch promo/banner slides (using announcements or static) ─
$slide_index = 0;

// ── Mark notifications as read on dashboard visit ───────────
mysqli_query($conn,
    "UPDATE notifications SET is_read = 1
     WHERE user_id = $user_id AND is_read = 0"
);

$page_title = 'Dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — Ligao Petcare</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        /* ── Banner / Carousel ── */
        .banner-carousel {
            position: relative;
            border-radius: var(--radius);
            overflow: hidden;
            background: linear-gradient(135deg, #1565c0, #00bcd4);
            min-height: 180px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: var(--shadow);
        }

        .banner-slide {
            display: none;
            width: 100%;
            padding: 28px 32px;
            color: #fff;
            text-align: center;
            animation: fadeIn 0.5s ease;
        }
        .banner-slide.active { display: flex; flex-direction: column; align-items: center; }

        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

        .banner-slide h2 {
            font-family: var(--font-head);
            font-size: 26px;
            font-weight: 800;
            margin-bottom: 6px;
            text-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }
        .banner-slide p {
            font-size: 14px;
            opacity: 0.9;
            margin-bottom: 16px;
        }
        .banner-badge {
            display: inline-block;
            background: #fff;
            color: var(--blue-header);
            font-weight: 800;
            font-size: 13px;
            padding: 6px 18px;
            border-radius: var(--radius-lg);
        }

        .banner-dots {
            position: absolute;
            bottom: 10px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            gap: 6px;
        }
        .banner-dot {
            width: 8px; height: 8px;
            border-radius: 50%;
            background: rgba(255,255,255,0.5);
            cursor: pointer;
            transition: var(--transition);
        }
        .banner-dot.active { background: #fff; transform: scale(1.3); }

        .banner-arrow {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(255,255,255,0.25);
            border: none;
            color: #fff;
            font-size: 18px;
            width: 32px; height: 32px;
            border-radius: 50%;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .banner-arrow:hover { background: rgba(255,255,255,0.45); }
        .banner-arrow.prev { left: 10px; }
        .banner-arrow.next { right: 10px; }

        /* ── Dashboard two-column layout ── */
        .dash-layout {
            display: grid;
            grid-template-columns: 1fr 320px;
            gap: 20px;
            align-items: start;
        }

        /* ── Appointment card ── */
        .appt-section {
            background: rgba(255,255,255,0.88);
            border-radius: var(--radius);
            padding: 20px;
            box-shadow: var(--shadow);
        }

        .appt-section-title {
            display: flex;
            align-items: center;
            gap: 10px;
            font-family: var(--font-head);
            font-size: 17px;
            font-weight: 700;
            margin-bottom: 16px;
            color: var(--text-dark);
        }

        .appt-item {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 12px 0;
            border-bottom: 1px solid var(--border);
        }
        .appt-item:last-of-type { border-bottom: none; }

        .appt-icon-wrap {
            width: 40px; height: 40px;
            border-radius: 10px;
            background: var(--teal-light);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            flex-shrink: 0;
        }

        .appt-info { flex: 1; }
        .appt-info .appt-date {
            font-size: 12px;
            color: var(--text-light);
            font-weight: 600;
        }
        .appt-info .appt-pet {
            font-weight: 700;
            font-size: 14px;
            color: var(--text-dark);
        }
        .appt-info .appt-service {
            font-size: 12px;
            color: var(--teal-dark);
            font-weight: 600;
        }

        .view-all-btn {
            display: flex;
            justify-content: flex-end;
            margin-top: 14px;
        }

        /* ── Announcement panel ── */
        .ann-panel {
            background: rgba(255,255,255,0.88);
            border-radius: var(--radius);
            padding: 20px;
            box-shadow: var(--shadow);
        }

        .ann-panel-title {
            font-family: var(--font-head);
            font-size: 22px;
            font-weight: 800;
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .ann-item {
            padding: 12px 0;
            border-bottom: 1px solid var(--border);
        }
        .ann-item:last-of-type { border-bottom: none; }

        .ann-item .ann-tag {
            font-size: 11px;
            font-weight: 800;
            color: var(--teal-dark);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }
        .ann-item .ann-title {
            font-weight: 700;
            font-size: 13px;
            color: var(--text-dark);
            margin-bottom: 4px;
        }
        .ann-item .ann-body {
            font-size: 13px;
            font-weight: 600;
            color: var(--text-mid);
            line-height: 1.6;
        }

        /* ── Quick links row ── */
        .quick-links {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            margin-bottom: 20px;
        }

        .quick-link-card {
            background: rgba(255,255,255,0.88);
            border-radius: var(--radius);
            padding: 16px 12px;
            text-align: center;
            box-shadow: var(--shadow);
            transition: var(--transition);
            cursor: pointer;
            text-decoration: none;
            color: var(--text-dark);
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 6px;
        }
        .quick-link-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
            border: 1.5px solid var(--teal);
        }
        .quick-link-card .ql-icon { font-size: 28px; }
        .quick-link-card .ql-label {
            font-weight: 700;
            font-size: 12px;
        }

        @media (max-width: 900px) {
            .dash-layout { grid-template-columns: 1fr; }
            .quick-links { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>
<body>
<div class="app-wrapper">

    <?php include '../includes/sidebar_user.php'; ?>

    <div class="main-content">

        <!-- Topbar -->
<div class="topbar">
    <div class="topbar-title" style="display:flex; align-items:center; gap:10px;">
        <img
            src="../assets/css/images/pets/logo.png"
            alt="Ligao Petcare Logo"
            style="width:36px; height:36px; border-radius:50%; object-fit:cover; border:2px solid rgba(255,255,255,0.6);"
            onerror="this.style.display='none';"
        >
        Welcome, <?= htmlspecialchars($user_name) ?>! 👋
    </div>
    <div class="topbar-actions">
        <a href="notifications.php" class="topbar-btn" title="Notifications">
            🔔
            <?php
            $notif_count = getUnreadNotifications($conn, $user_id);
            if ($notif_count > 0):
            ?>
                <span class="badge-count"><?= $notif_count ?></span>
            <?php endif; ?>
        </a>
        <a href="settings.php" class="topbar-btn" title="Settings">⚙️</a>
    </div>
</div>
        <!-- Page Body -->
        <div class="page-body">

            <!-- Banner + Announcements -->
            <div class="dash-layout">
                <div>
                    <!-- Promo Banner Carousel -->
                    <div class="banner-carousel" style="margin-bottom:20px;">
                        <!-- Slide 1 -->
                        <div class="banner-slide active">
                            <div style="font-size:36px;margin-bottom:8px;">🐶🐱</div>
                            <h2>PETCARE PRODUCTS PROMO!</h2>
                            <p>Best Deals for Your Pets! — Grooming Supplies, Pet Accessories & Healthy Pet Foods</p>
                            <a href="products.php" class="banner-badge">🛍️ Shop Now &amp; Save!</a>
                        </div>
                        <!-- Slide 2 -->
                        <div class="banner-slide">
                            <div style="font-size:36px;margin-bottom:8px;">💉</div>
                            <h2>VACCINATION AVAILABLE</h2>
                            <p>Keep your pets safe. Book a vaccination appointment today!</p>
                            <a href="appointments.php" class="banner-badge">📅 Book Now</a>
                        </div>
                        <!-- Slide 3 -->
                        <div class="banner-slide">
                            <div style="font-size:36px;margin-bottom:8px;">🏠</div>
                            <h2>HOME SERVICE NOW AVAILABLE</h2>
                            <p>Vet care at your doorstep — Ligao City, Oas, and Polangui Albay</p>
                            <a href="appointments.php" class="banner-badge">📋 Schedule Now</a>
                        </div>

                        <!-- Arrows -->
                        <button class="banner-arrow prev" onclick="prevSlide()">‹</button>
                        <button class="banner-arrow next" onclick="nextSlide()">›</button>

                        <!-- Dots -->
                        <div class="banner-dots">
                            <div class="banner-dot active" onclick="goToSlide(0)"></div>
                            <div class="banner-dot" onclick="goToSlide(1)"></div>
                            <div class="banner-dot" onclick="goToSlide(2)"></div>
                        </div>
                    </div>

                    <!-- Upcoming Appointments -->
                    <div class="appt-section">
                        <div class="appt-section-title">
                            <span>📅</span> Appointment
                        </div>

                        <?php if (empty($appointments)): ?>
                            <div class="empty-state" style="padding:24px;">
                                <div class="empty-icon">📭</div>
                                <h3>No Upcoming Appointments</h3>
                                <p>You don't have any appointments scheduled.</p>
                                <a href="appointments.php" class="btn btn-teal btn-sm" style="margin-top:12px;">
                                    + Book Appointment
                                </a>
                            </div>
                        <?php else: ?>
                            <?php foreach ($appointments as $appt): ?>
                                <div class="appt-item">
                                    <div class="appt-icon-wrap">📅</div>
                                    <div class="appt-info">
                                        <div class="appt-date">
                                            <?= formatDate($appt['appointment_date']) ?> |
                                            <?= formatTime($appt['appointment_time']) ?>
                                        </div>
                                        <div class="appt-pet">
                                            <?= htmlspecialchars($appt['pet_name'] ?? '—') ?>
                                        </div>
                                        <div class="appt-service">
                                            <?= htmlspecialchars($appt['service_name'] ?? $appt['appointment_type']) ?>
                                            — <?= statusBadge($appt['status']) ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>

                            <div class="view-all-btn">
                                <a href="appointments.php" class="btn btn-teal btn-sm">View ALL</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Announcements Panel -->
                <div class="ann-panel">
                    <div class="ann-panel-title">
                        <span>📢</span> Announcement
                    </div>

                    <?php if (empty($announcements)): ?>
                        <p style="font-size:13px;color:var(--text-light);">No announcements at this time.</p>
                    <?php else: ?>
                        <?php foreach ($announcements as $ann): ?>
                            <div class="ann-item">
                                <div class="ann-tag">Attention Pet Owners!</div>
                                <div class="ann-title"><?= htmlspecialchars($ann['title']) ?></div>
                                <div class="ann-body"><?= nl2br(htmlspecialchars($ann['content'])) ?></div>
                                <div style="font-size:11px;color:var(--text-light);margin-top:4px;">
                                    <?= formatDate($ann['created_at']) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <div class="view-all-btn" style="margin-top:10px;">
                        <a href="announcements.php" class="btn btn-yellow btn-sm">View ALL</a>
                    </div>
                </div>
            </div>

        </div><!-- /page-body -->
    </div><!-- /main-content -->
</div><!-- /app-wrapper -->

<script src="../assets/js/main.js"></script>
<script>
// ── Banner Carousel ──────────────────────────────────────────
let currentSlide = 0;
const slides = document.querySelectorAll('.banner-slide');
const dots   = document.querySelectorAll('.banner-dot');
let autoSlide;

function showSlide(n) {
    slides.forEach(s => s.classList.remove('active'));
    dots.forEach(d => d.classList.remove('active'));
    currentSlide = (n + slides.length) % slides.length;
    slides[currentSlide].classList.add('active');
    dots[currentSlide].classList.add('active');
}

function nextSlide() { showSlide(currentSlide + 1); resetAuto(); }
function prevSlide() { showSlide(currentSlide - 1); resetAuto(); }
function goToSlide(n) { showSlide(n); resetAuto(); }

function resetAuto() {
    clearInterval(autoSlide);
    autoSlide = setInterval(() => showSlide(currentSlide + 1), 4000);
}

// Auto-rotate every 4 seconds
autoSlide = setInterval(() => showSlide(currentSlide + 1), 4000);
</script>
</body>
</html>