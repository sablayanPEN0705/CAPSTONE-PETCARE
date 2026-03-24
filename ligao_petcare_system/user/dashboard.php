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

// ── Fetch upcoming appointments ──────────────────────────────
$appointments = getRows($conn,
    "SELECT a.*, p.name AS pet_name, s.name AS service_name
     FROM appointments a
     LEFT JOIN pets p ON a.pet_id = p.id
     LEFT JOIN services s ON a.service_id = s.id
     WHERE a.user_id = $user_id
       AND a.status IN ('pending','confirmed','completed')
     ORDER BY FIELD(a.status,'confirmed','pending','completed'),
              a.appointment_date ASC, a.appointment_time ASC
     LIMIT 5"
);
// ── Fetch latest announcements ───────────────────────────────
$announcements = getRows($conn,
    "SELECT * FROM announcements ORDER BY created_at DESC LIMIT 3"
);

// ── Fetch active promotions for banner carousel ───────────────
// Checks if table exists first (safe on fresh installs)
$banner_slides = [];
$tbl_check = mysqli_query($conn, "SHOW TABLES LIKE 'promotions'");
if ($tbl_check && mysqli_num_rows($tbl_check) > 0) {
    $banner_slides = getRows($conn,
        "SELECT * FROM promotions WHERE is_active = 1
         ORDER BY sort_order ASC, id ASC");
}

// Fallback: show default slides if admin hasn't added any yet
if (empty($banner_slides)) {
    $banner_slides = [
        ['icon' => '🐶🐱', 'title' => 'PETCARE PRODUCTS PROMO!',
         'description' => 'Best Deals for Your Pets! — Grooming Supplies, Pet Accessories & Healthy Pet Foods',
         'link_label' => '🛍️ Shop Now & Save!', 'link_url' => 'products.php'],
        ['icon' => '💉',   'title' => 'VACCINATION AVAILABLE',
         'description' => 'Keep your pets safe. Book a vaccination appointment today!',
         'link_label' => '📅 Book Now', 'link_url' => 'appointments.php'],
        ['icon' => '🏠',   'title' => 'HOME SERVICE NOW AVAILABLE',
         'description' => 'Vet care at your doorstep — Ligao City, Oas, and Polangui Albay',
         'link_label' => '📋 Schedule Now', 'link_url' => 'appointments.php'],
    ];
}

// ── Mark notifications as read ───────────────────────────────
mysqli_query($conn,
    "UPDATE notifications SET is_read=1 WHERE user_id=$user_id AND is_read=0");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — Ligao Petcare</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>

.appt-status-badge {
    flex-shrink: 0; padding: 4px 12px;
    border-radius: 20px; font-size: 11px; font-weight: 800;
    text-transform: uppercase; letter-spacing: 0.5px;
}
.appt-status-pending   { background: #fff8e1; color: #f59e0b; border: 1.5px solid #fde68a; }
.appt-status-confirmed { background: #e0f7fa; color: #0097a7; border: 1.5px solid #b2ebf2; }
.appt-status-completed { background: #e8f5e9; color: #2e7d32; border: 1.5px solid #a5d6a7; }
.appt-status-cancelled { background: #fef2f2; color: #dc2626; border: 1.5px solid #fecaca; }

.appt-item-completed { opacity: 0.75; }
.appt-item-completed .appt-icon-wrap { background: #e8f5e9; }

        /* Banner Carousel */
        .banner-carousel {
            position: relative; border-radius: var(--radius); overflow: hidden;
            background: linear-gradient(135deg, #1565c0, #00bcd4);
            min-height: 180px; display: flex; align-items: center; justify-content: center;
            box-shadow: var(--shadow);
        }
        .banner-slide {
            display: none; width: 100%; padding: 28px 32px;
            color: #fff; text-align: center; animation: fadeIn 0.5s ease;
            flex-direction: column; align-items: center;
        }
        .banner-slide.active { display: flex; }
        @keyframes fadeIn { from { opacity:0; } to { opacity:1; } }
        .banner-slide h2 {
            font-family: var(--font-head); font-size: 26px; font-weight: 800;
            margin-bottom: 6px; text-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }
        .banner-slide p { font-size: 14px; opacity: 0.9; margin-bottom: 16px; }
        .banner-badge {
            display: inline-block; background: #fff; color: var(--blue-header);
            font-weight: 800; font-size: 13px; padding: 6px 18px;
            border-radius: var(--radius-lg); text-decoration: none; transition: var(--transition);
        }
        .banner-badge:hover { background: #e0f7fa; }
        .banner-dots {
            position: absolute; bottom: 10px; left: 50%; transform: translateX(-50%);
            display: flex; gap: 6px;
        }
        .banner-dot {
            width: 8px; height: 8px; border-radius: 50%;
            background: rgba(255,255,255,0.5); cursor: pointer; transition: var(--transition);
        }
        .banner-dot.active { background: #fff; transform: scale(1.3); }
        .banner-arrow {
            position: absolute; top: 50%; transform: translateY(-50%);
            background: rgba(255,255,255,0.25); border: none; color: #fff;
            font-size: 18px; width: 32px; height: 32px; border-radius: 50%;
            cursor: pointer; transition: var(--transition);
            display: flex; align-items: center; justify-content: center;
        }
        .banner-arrow:hover { background: rgba(255,255,255,0.45); }
        .banner-arrow.prev { left: 10px; }
        .banner-arrow.next { right: 10px; }

        /* Layout */
        .dash-layout { display: grid; grid-template-columns: 1fr 320px; gap: 20px; align-items: start; }
        .appt-section { background: rgba(255,255,255,0.88); border-radius: var(--radius); padding: 20px; box-shadow: var(--shadow); }
        .appt-section-title { display: flex; align-items: center; gap: 10px; font-family: var(--font-head); font-size: 17px; font-weight: 700; margin-bottom: 16px; color: var(--text-dark); }
        .appt-item { display: flex; align-items: center; gap: 14px; padding: 12px 0; border-bottom: 1px solid var(--border); }
        .appt-item:last-of-type { border-bottom: none; }
        .appt-icon-wrap { width: 40px; height: 40px; border-radius: 10px; background: var(--teal-light); display: flex; align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0; }
        .appt-info { flex: 1; }
        .appt-info .appt-date    { font-size: 12px; color: var(--text-light); font-weight: 600; }
        .appt-info .appt-pet     { font-weight: 700; font-size: 14px; color: var(--text-dark); }
        .appt-info .appt-service { font-size: 12px; color: var(--teal-dark); font-weight: 600; }
        .view-all-btn { display: flex; justify-content: flex-end; margin-top: 14px; }

        .ann-panel { background: rgba(255,255,255,0.88); border-radius: var(--radius); padding: 20px; box-shadow: var(--shadow); }
        .ann-panel-title { font-family: var(--font-head); font-size: 22px; font-weight: 800; margin-bottom: 14px; display: flex; align-items: center; gap: 8px; }
        .ann-item { padding: 12px 0; border-bottom: 1px solid var(--border); }
        .ann-item:last-of-type { border-bottom: none; }
        .ann-item .ann-tag   { font-size: 11px; font-weight: 800; color: var(--teal-dark); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px; }
        .ann-item .ann-title { font-weight: 700; font-size: 13px; color: var(--text-dark); margin-bottom: 4px; }
        .ann-item .ann-body  { font-size: 13px; font-weight: 600; color: var(--text-mid); line-height: 1.6; }

        @media(max-width:900px) { .dash-layout { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<div class="app-wrapper">
    <?php include '../includes/sidebar_user.php'; ?>

    <div class="main-content">
        <div class="topbar">
            <div class="topbar-title" style="display:flex; align-items:center; gap:10px;">
                <img src="../assets/css/images/pets/logo.png" alt="Ligao Petcare Logo"
                     style="width:36px;height:36px;border-radius:50%;object-fit:cover;border:2px solid rgba(255,255,255,0.6);"
                     onerror="this.style.display='none';">
                Welcome, <?= htmlspecialchars($user_name) ?>! 👋
            </div>
            <div class="topbar-actions">
                <a href="notifications.php" class="topbar-btn">
                    🔔
                    <?php $notif_count = getUnreadNotifications($conn, $user_id); if ($notif_count > 0): ?>
                        <span class="badge-count"><?= $notif_count ?></span>
                    <?php endif; ?>
                </a>
                <a href="settings.php" class="topbar-btn">⚙️</a>
            </div>
        </div>

        <div class="page-body">
            <div class="dash-layout">
                <div>
                    <!-- ── Dynamic Banner Carousel (from promotions table) ── -->
                    <div class="banner-carousel" style="margin-bottom:20px;">

                        <?php foreach ($banner_slides as $idx => $slide): ?>
                            <div class="banner-slide <?= $idx === 0 ? 'active' : '' ?>">
                                <div style="font-size:36px;margin-bottom:8px;">
                                    <?= htmlspecialchars($slide['icon']) ?>
                                </div>
                                <h2><?= htmlspecialchars($slide['title']) ?></h2>
                                <p><?= htmlspecialchars($slide['description']) ?></p>
                                <a href="<?= htmlspecialchars($slide['link_url']) ?>"
                                   class="banner-badge">
                                    <?= htmlspecialchars($slide['link_label']) ?>
                                </a>
                            </div>
                        <?php endforeach; ?>

                        <?php if (count($banner_slides) > 1): ?>
                            <button class="banner-arrow prev" onclick="prevSlide()">‹</button>
                            <button class="banner-arrow next" onclick="nextSlide()">›</button>
                            <div class="banner-dots">
                                <?php foreach ($banner_slides as $idx => $s): ?>
                                    <div class="banner-dot <?= $idx === 0 ? 'active' : '' ?>"
                                         onclick="goToSlide(<?= $idx ?>)"></div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Upcoming Appointments -->
                    <div class="appt-section">
                        <div class="appt-section-title"><span>📅</span> Appointment</div>

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
                            <?php foreach ($appointments as $appt): 
    $status_icon = match($appt['status']) {
        'confirmed' => '✅',
        'pending'   => '⏳',
        'completed' => '🏁',
        default     => '📅',
    };
    $is_completed = $appt['status'] === 'completed';
?>
    <div class="appt-item <?= $is_completed ? 'appt-item-completed' : '' ?>">
        <div class="appt-icon-wrap">
            <?= $status_icon ?>
        </div>
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
            </div>
        </div>
        <div class="appt-status-badge appt-status-<?= $appt['status'] ?>">
            <?= ucfirst($appt['status']) ?>
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
                    <div class="ann-panel-title"><span>📢</span> Announcement</div>

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
        </div>
    </div>
</div>

<script src="../assets/js/main.js"></script>
<script>
// ── Banner Carousel ──────────────────────────────────────────
let currentSlide = 0;
const slides = document.querySelectorAll('.banner-slide');
const dots   = document.querySelectorAll('.banner-dot');
let autoSlide;

function showSlide(n) {
    if (!slides.length) return;
    slides.forEach(s => s.classList.remove('active'));
    dots.forEach(d => d.classList.remove('active'));
    currentSlide = (n + slides.length) % slides.length;
    slides[currentSlide].classList.add('active');
    if (dots[currentSlide]) dots[currentSlide].classList.add('active');
}

function nextSlide() { showSlide(currentSlide + 1); resetAuto(); }
function prevSlide() { showSlide(currentSlide - 1); resetAuto(); }
function goToSlide(n){ showSlide(n); resetAuto(); }

function resetAuto() {
    clearInterval(autoSlide);
    if (slides.length > 1) autoSlide = setInterval(() => showSlide(currentSlide + 1), 4000);
}

if (slides.length > 1) {
    autoSlide = setInterval(() => showSlide(currentSlide + 1), 4000);
}
</script>
</body>
</html>