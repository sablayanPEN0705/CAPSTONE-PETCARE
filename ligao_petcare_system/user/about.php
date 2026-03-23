<?php
// ============================================================
// Ligao Petcare & Veterinary Clinic
// File: user/about.php
// Purpose: About Us page — clinic info, staff, contact
// ============================================================
require_once '../includes/auth.php';
requireLogin();
if (isAdmin()) redirect('../admin/dashboard.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us — Ligao Petcare</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .about-card {
            background: rgba(255,255,255,0.92);
            border-radius: var(--radius);
            padding: 36px 40px;
            box-shadow: var(--shadow);
            max-width: 780px;
            margin: 0 auto;
        }

        .about-card h2 {
            font-family: var(--font-head);
            font-size: 28px;
            font-weight: 800;
            text-align: center;
            margin-bottom: 28px;
            color: var(--text-dark);
        }

        .about-paragraph {
            font-size: 14px;
            color: var(--text-mid);
            line-height: 1.85;
            text-align: center;
            margin-bottom: 20px;
            font-weight: 500;
        }

        .about-paragraph strong {
            color: var(--text-dark);
            font-weight: 700;
        }

        .divider-line {
            border: none;
            border-top: 1px solid var(--border);
            margin: 24px 0;
        }

        .contact-section {
            text-align: center;
        }

        .contact-section p {
            font-size: 14px;
            color: var(--text-mid);
            font-weight: 600;
            margin-bottom: 10px;
        }

        .contact-numbers {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
        }

        .contact-number {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--teal-light);
            color: var(--teal-dark);
            padding: 8px 22px;
            border-radius: var(--radius-lg);
            font-weight: 800;
            font-size: 15px;
            transition: var(--transition);
            text-decoration: none;
        }

        .contact-number:hover {
            background: var(--teal);
            color: #fff;
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin: 24px 0;
        }

        .info-box {
            background: linear-gradient(135deg, rgba(0,188,212,0.08), rgba(0,188,212,0.03));
            border: 1.5px solid var(--teal-light);
            border-radius: var(--radius);
            padding: 18px 20px;
            text-align: center;
        }

        .info-box .ib-icon {
            font-size: 32px;
            margin-bottom: 8px;
        }

        .info-box .ib-label {
            font-size: 11px;
            font-weight: 700;
            color: var(--text-light);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }

        .info-box .ib-value {
            font-weight: 700;
            font-size: 13px;
            color: var(--text-dark);
            line-height: 1.5;
        }

        .staff-section {
            margin: 24px 0;
        }

        .staff-section h3 {
            font-family: var(--font-head);
            font-size: 16px;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 14px;
            text-align: center;
        }

        .staff-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 12px;
        }

        .staff-card {
            background: #fff;
            border-radius: var(--radius);
            padding: 16px 12px;
            text-align: center;
            box-shadow: var(--shadow);
        }

        .staff-card .sc-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--teal), #0097a7);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            margin: 0 auto 8px;
            color: #fff;
        }

        .staff-card .sc-role {
            font-size: 11px;
            color: var(--text-light);
            font-weight: 600;
        }

        .staff-card .sc-name {
            font-weight: 700;
            font-size: 12px;
            color: var(--text-dark);
        }

        .fb-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #1877f2;
            font-weight: 700;
            font-size: 14px;
            text-decoration: none;
            background: #e7f0fd;
            padding: 8px 20px;
            border-radius: var(--radius-lg);
            transition: var(--transition);
            margin-top: 8px;
        }
        .fb-link:hover {
            background: #1877f2;
            color: #fff;
        }

        @media(max-width: 600px) {
            .about-card { padding: 24px 18px; }
            .info-grid  { grid-template-columns: 1fr; }
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
        <img src="../assets/css/images/pets/logo.png" alt="Logo"
             style="width:36px; height:36px; border-radius:50%; object-fit:cover; border:2px solid rgba(255,255,255,0.6);"
             onerror="this.style.display='none';">
        About Us
    </div>
            <div class="topbar-actions">
                <a href="notifications.php" class="topbar-btn">🔔</a>
                <a href="settings.php"      class="topbar-btn">⚙️</a>
            </div>
        </div>

        <div class="page-body">

            <div class="about-card">
                <h2>About Us</h2>

                <!-- Main Description -->
                <p class="about-paragraph">
                    <strong>Ligao Petcare & Veterinary Clinic</strong> is a veterinary service provider
                    located at <strong>National Highway, Zone 4, Tuburan, Ligao City, Albay.</strong>
                    The clinic is managed by <strong>Dr. Ann Lawrence S. Polidario</strong>, a licensed
                    Doctor of Veterinary Medicine dedicated to providing quality healthcare for pets
                    and animals.
                </p>

                <!-- Info Grid -->
                <div class="info-grid">
                    <div class="info-box">
                        <div class="ib-icon">📍</div>
                        <div class="ib-label">Location</div>
                        <div class="ib-value">
                            National Highway, Zone 4,<br>
                            Tuburan, Ligao City, Albay
                        </div>
                    </div>
                    <div class="info-box">
                        <div class="ib-icon">🏥</div>
                        <div class="ib-label">Service Areas</div>
                        <div class="ib-value">
                            Ligao City · Oas · Polangui<br>
                            (Home Service Available)
                        </div>
                    </div>
                    <div class="info-box">
                        <div class="ib-icon">⏰</div>
                        <div class="ib-label">Clinic Hours</div>
                        <div class="ib-value">
                            Mon – Sat: 8:00 AM – 6:00 PM<br>
                            Sun: By Appointment
                        </div>
                    </div>
                    <div class="info-box">
                        <div class="ib-icon">🐾</div>
                        <div class="ib-label">Specialization</div>
                        <div class="ib-value">
                            Dogs & Cats<br>
                            Small Animals
                        </div>
                    </div>
                </div>

                <hr class="divider-line">

                <!-- Staff Description -->
                <p class="about-paragraph">
                    The clinic is supported by a team of <strong>five staff members</strong>,
                    including the veterinarian, assistant staff, one groomer, and one multitasker,
                    who work together to ensure efficient and compassionate service for pet owners.
                </p>

                <!-- Staff Cards -->
                <div class="staff-section">
                    <h3>Our Team</h3>
                    <div class="staff-grid">
                        <div class="staff-card">
                            <div class="sc-avatar">👩‍⚕️</div>
                            <div class="sc-role">Veterinarian</div>
                            <div class="sc-name">Dr. Ann Lawrence S. Polidario</div>
                        </div>
                        <div class="staff-card">
                            <div class="sc-avatar">🩺</div>
                            <div class="sc-role">Vet Assistant</div>
                            <div class="sc-name">Clinic Staff</div>
                        </div>
                        <div class="staff-card">
                            <div class="sc-avatar">✂️</div>
                            <div class="sc-role">Groomer</div>
                            <div class="sc-name">Grooming Specialist</div>
                        </div>
                        <div class="staff-card">
                            <div class="sc-avatar">📋</div>
                            <div class="sc-role">Multitasker</div>
                            <div class="sc-name">Operations Staff</div>
                        </div>
                    </div>
                </div>

                <hr class="divider-line">

                <!-- Online Presence -->
                <p class="about-paragraph">
                    <strong>Ligao Petcare & Veterinary Clinic</strong> also maintains an online
                    presence through its Facebook page, where clients can access updates and
                    information about the clinic.
                </p>

                <div style="text-align:center;margin-bottom:24px;">
                    <a href="https://www.facebook.com/LPCVC"
                       target="_blank" rel="noopener"
                       class="fb-link">
                        📘 Ligao Petcare &amp; Veterinary Clinic
                    </a>
                </div>

                <hr class="divider-line">

                <!-- Contact -->
                <div class="contact-section">
                    <p>For inquiries or appointments, clients may contact the clinic
                       through the following numbers:</p>
                    <div class="contact-numbers">
                        <a href="tel:09263967678" class="contact-number">
                            📞 0926-396-7678
                        </a>
                        <a href="tel:09501381530" class="contact-number">
                            📞 0950-138-1530
                        </a>
                    </div>
                    <div style="margin-top:20px;">
                        <a href="messages.php" class="btn btn-teal">
                            💬 Message Us Now
                        </a>
                    </div>
                </div>

            </div><!-- /about-card -->

        </div><!-- /page-body -->
    </div>
</div>

<script src="../assets/js/main.js"></script>
</body>
</html>