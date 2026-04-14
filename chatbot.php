<?php
// ============================================================
// Ligao Petcare & Veterinary Clinic
// File: chatbot.php  (place in ROOT folder, same as index.php)
// Purpose: AI Chatbot API endpoint — powered by Google Gemini
// ============================================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/config.php';        // gives $conn
require_once __DIR__ . '/includes/auth.php'; // gives sanitize(), getRow(), etc.

// ── Only accept POST requests ─────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// ── Read incoming JSON body ───────────────────────────────────
$input   = json_decode(file_get_contents('php://input'), true);
$message = trim($input['message'] ?? '');
$history = $input['history'] ?? [];

if ($message === '') {
    echo json_encode(['error' => 'Empty message']);
    exit;
}

// ── Build system prompt with live clinic data ─────────────────
$system = buildSystemPrompt($conn);

// ── Call Google Gemini API ────────────────────────────────────
$apiKey = 'AIzaSyCDAjLCGKJrLK09FbyoVJc1xW-kKKfqhTs'; // ← your Gemini key
$model  = 'gemini-2.5-flash';

// Build conversation history for multi-turn context
$turns = [];
foreach (array_slice($history, -8) as $h) {
    $turns[] = [
        'role'  => ($h['role'] === 'assistant') ? 'model' : 'user',
        'parts' => [['text' => $h['content']]]
    ];
}
// Append current user message
$turns[] = [
    'role'  => 'user',
    'parts' => [['text' => $message]]
];

$payload = [
    'system_instruction' => [
        'parts' => [['text' => $system]]
    ],
    'contents'           => $turns,
    'generationConfig'   => [
        'maxOutputTokens' => 600,
        'temperature'     => 0.7,
    ]
];

$ch = curl_init(
    "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}"
);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_TIMEOUT        => 30,
]);

$raw  = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// ── Handle API errors ─────────────────────────────────────────
if ($raw === false || $code !== 200) {
    $err = json_decode($raw, true);
    $msg = $err['error']['message'] ?? 'AI service unavailable. Please try again later.';
    echo json_encode(['error' => $msg]);
    exit;
}

$data  = json_decode($raw, true);
$reply = $data['candidates'][0]['content']['parts'][0]['text']
         ?? 'Sorry, I could not generate a response. Please try again.';

// ── THIS WAS MISSING — send reply back to the widget ─────────
echo json_encode(['reply' => $reply]);
exit;

// =============================================================
// buildSystemPrompt() — pulls live data from the database
// =============================================================
function buildSystemPrompt(mysqli $conn): string
{
    // Services
    $services = '';
    $res = $conn->query(
        "SELECT name, description, price_min, price_max, category, status
         FROM services ORDER BY category, name"
    );
    while ($row = $res->fetch_assoc()) {
        $price = ($row['price_min'] == $row['price_max'])
            ? '₱' . number_format($row['price_min'], 2)
            : '₱' . number_format($row['price_min'], 2) . ' – ₱' . number_format($row['price_max'], 2);
        $avail    = $row['status'] === 'available' ? 'Available' : 'Not Available';
        $services .= "- {$row['name']} ({$row['category']}): {$row['description']} | Price: {$price} | {$avail}\n";
    }

    // Products
    $products = '';
    $res2 = $conn->query(
        "SELECT name, category, price, quantity, status
         FROM products ORDER BY category, name"
    );
    while ($row = $res2->fetch_assoc()) {
        $products .= "- {$row['name']} ({$row['category']}): ₱{$row['price']} | Qty: {$row['quantity']} | {$row['status']}\n";
    }

    // Latest Announcements
    $announcements = '';
    $res3 = $conn->query(
        "SELECT title, content, created_at FROM announcements
         ORDER BY created_at DESC LIMIT 3"
    );
    while ($row = $res3->fetch_assoc()) {
        $date           = date('F j, Y', strtotime($row['created_at']));
        $announcements .= "- [{$date}] {$row['title']}: {$row['content']}\n";
    }

    return <<<PROMPT
You are Petey 🐾, the friendly AI assistant for Ligao Petcare & Veterinary Clinic.

CLINIC INFO:
- Name: Ligao Petcare & Veterinary Clinic
- Veterinarian: Dr. Ann Lawrence S. Polidario
- Address: National Highway, Zone 4, Tuburan, Ligao City, Albay
- Contact: 0926-396-7678
- Email: admin@ligaopetcare.com

YOUR ROLE — help pet owners with:
1. Clinic services and pricing
2. Products available at the clinic
3. General pet health questions (dogs, cats)
4. How to book appointments (clinic or home service)
5. Clinic location, contact, and latest announcements
6. Emergency guidance (when to see a vet urgently)

CURRENT SERVICES:
{$services}

CURRENT PRODUCTS:
{$products}

LATEST ANNOUNCEMENTS:
{$announcements}

HOW TO BOOK AN APPOINTMENT:
- Log in to the system and click "Book Appointment"
- Choose: Clinic Visit or Home Service
- Select your pet, service, preferred date & time
- Home service supports multiple pets in one visit
- Service area: Ligao City, Oas, and Polangui, Albay

BEHAVIOR RULES:
- Be warm, friendly, and concise 🐶🐱
- Use simple language; emojis are welcome
- For serious symptoms (difficulty breathing, seizures, blood in stool/urine, collapse, severe vomiting, suspected poisoning) — always urge the owner to visit the clinic or call immediately
- Keep answers under 200 words unless a detailed explanation is truly needed
- You do NOT have access to a specific user's appointments, pet records, or billing — direct those to the dashboard or clinic staff
- If unsure about something clinic-specific, say so honestly and suggest calling 0926-396-7678
PROMPT;
}
?>