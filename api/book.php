<?php
/**
 * Appointment booking endpoint.
 * Accepts POST (JSON or form-encoded), validates, stores a copy, emails the clinic.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/mailer.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

/** Emit a JSON response and stop. */
function hh_respond(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    hh_respond(405, ['ok' => false, 'message' => 'Method not allowed.']);
}

// ---------------------------------------------------------------------------
// Read input (JSON body or classic form post)
// ---------------------------------------------------------------------------
$input = $_POST;
if (empty($input)) {
    $raw = file_get_contents('php://input') ?: '';
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        $input = $decoded;
    }
}

function hh_in(array $input, string $key, int $maxLen = 500): string
{
    $value = isset($input[$key]) && is_scalar($input[$key]) ? (string) $input[$key] : '';
    $value = trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '');

    return mb_substr($value, 0, $maxLen);
}

// ---------------------------------------------------------------------------
// Spam guards
// ---------------------------------------------------------------------------

// 1. Honeypot — real users never see or fill this field.
if (hh_in($input, 'website') !== '') {
    hh_respond(200, ['ok' => true, 'message' => 'Thank you. We will call you shortly.']);
}

// 2. Time trap — a genuine person takes more than 3 seconds to fill the form.
$renderedAt = (int) hh_in($input, 'rendered_at', 20);
if ($renderedAt > 0 && (time() - (int) ($renderedAt / 1000)) < 3) {
    hh_respond(429, ['ok' => false, 'message' => 'That was too quick. Please try again.']);
}

// 3. Per-IP rate limit — 5 submissions per hour.
$ip        = (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
$rateFile  = dirname(__DIR__) . '/storage/cache/rate_' . md5($ip) . '.json';
$now       = time();
$hits      = [];

if (is_file($rateFile)) {
    $stored = json_decode((string) file_get_contents($rateFile), true);
    if (is_array($stored)) {
        $hits = array_values(array_filter($stored, static fn($t) => is_int($t) && ($now - $t) < 3600));
    }
}

if (count($hits) >= 5) {
    hh_respond(429, [
        'ok'      => false,
        'message' => 'You have sent several requests already. Please call us on ' . HH_PHONE_PRETTY . '.',
    ]);
}

// ---------------------------------------------------------------------------
// Validate
// ---------------------------------------------------------------------------
$errors = [];

$name = hh_in($input, 'name', 120);
if (mb_strlen($name) < 2) {
    $errors['name'] = 'Please enter your full name.';
}

$phoneRaw    = hh_in($input, 'phone', 25);
$phoneDigits = preg_replace('/\D+/', '', $phoneRaw) ?? '';
if (strlen($phoneDigits) < 10 || strlen($phoneDigits) > 15) {
    $errors['phone'] = 'Please enter a valid phone number with country code.';
}

$email = hh_in($input, 'email', 160);
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'Please enter a valid email address.';
}

$age = hh_in($input, 'age', 3);
if ($age !== '' && (!ctype_digit($age) || (int) $age < 1 || (int) $age > 120)) {
    $errors['age'] = 'Please enter a valid age.';
}

$service = hh_in($input, 'service', 40);
if (!array_key_exists($service, HH_SERVICES) && $service !== 'other') {
    $errors['service'] = 'Please choose what you would like help with.';
}
$serviceLabel = $service === 'other'
    ? 'Other / general consultation'
    : (HH_SERVICES[$service]['title'] ?? $service);

$mode = hh_in($input, 'mode', 20);
if (!array_key_exists($mode, HH_CONSULT_MODES)) {
    $errors['mode'] = 'Please choose how you would like to consult.';
}
$modeLabel = HH_CONSULT_MODES[$mode] ?? $mode;

$date = hh_in($input, 'date', 10);
$dateObj = DateTimeImmutable::createFromFormat('!Y-m-d', $date) ?: null;
if ($dateObj === null) {
    $errors['date'] = 'Please choose a preferred date.';
} else {
    $today = new DateTimeImmutable('today');
    if ($dateObj < $today) {
        $errors['date'] = 'Please choose today or a future date.';
    } elseif ($dateObj > $today->modify('+6 months')) {
        $errors['date'] = 'Please choose a date within the next six months.';
    }
}

$slot = hh_in($input, 'slot', 40);
if (!in_array($slot, HH_TIME_SLOTS, true)) {
    $errors['slot'] = 'Please choose a preferred time slot.';
}

$message = hh_in($input, 'message', 1500);

if ($errors !== []) {
    hh_respond(422, [
        'ok'      => false,
        'message' => 'Please correct the highlighted fields.',
        'errors'  => $errors,
    ]);
}

// ---------------------------------------------------------------------------
// Assemble the booking
// ---------------------------------------------------------------------------
$booking = [
    'reference'     => 'HH-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6)),
    'name'          => $name,
    'phone'         => $phoneRaw,
    'email'         => $email,
    'age'           => $age,
    'service'       => $service,
    'service_label' => $serviceLabel,
    'mode'          => $mode,
    'mode_label'    => $modeLabel,
    'date'          => $date,
    'date_pretty'   => $dateObj instanceof DateTimeImmutable ? $dateObj->format('l, d F Y') : $date,
    'slot'          => $slot,
    'message'       => $message,
    'submitted_at'  => (new DateTimeImmutable('now', new DateTimeZone('Asia/Kolkata')))->format('d M Y, h:i A') . ' IST',
    'ip'            => $ip,
];

// ---------------------------------------------------------------------------
// Persist a local copy first — email can fail, the lead should not be lost.
// ---------------------------------------------------------------------------
if (!is_dir(HH_BOOKINGS_DIR)) {
    @mkdir(HH_BOOKINGS_DIR, 0775, true);
}
$logFile = HH_BOOKINGS_DIR . '/' . date('Y-m') . '.jsonl';
@file_put_contents(
    $logFile,
    json_encode($booking, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL,
    FILE_APPEND | LOCK_EX
);

// Record the rate-limit hit.
$hits[] = $now;
@file_put_contents($rateFile, json_encode($hits), LOCK_EX);

// ---------------------------------------------------------------------------
// Send mail
// ---------------------------------------------------------------------------
try {
    hh_send_booking_to_clinic($booking, $HH_MAIL);
} catch (Throwable $ex) {
    error_log('[HealHealth] Clinic mail failed: ' . $ex->getMessage());
    hh_respond(500, [
        'ok'        => false,
        'message'   => 'We could not send your request just now. Please call us on ' . HH_PHONE_PRETTY . ' — we have saved your details.',
        'reference' => $booking['reference'],
    ]);
}

if ($HH_MAIL['send_patient_copy']) {
    try {
        hh_send_patient_ack($booking, $HH_MAIL);
    } catch (Throwable $ex) {
        // The clinic already has the request; a failed acknowledgement is not fatal.
        error_log('[HealHealth] Patient ack failed: ' . $ex->getMessage());
    }
}

hh_respond(200, [
    'ok'        => true,
    'message'   => 'Thank you, ' . $booking['name'] . '. Your request is with us — we will call you to confirm the time.',
    'reference' => $booking['reference'],
]);
