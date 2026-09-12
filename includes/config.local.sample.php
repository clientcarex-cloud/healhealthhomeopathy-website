<?php
/**
 * Copy this file to config.local.php and fill in your real credentials.
 * config.local.php is git-ignored and must never be committed.
 */

return [
    // ---- Where bookings are delivered -------------------------------------
    'HH_MAIL_TO'      => 'digicarelynx@gmail.com',
    'HH_MAIL_TO_NAME' => 'Heal Health Homeopathy',

    // ---- Envelope sender --------------------------------------------------
    // Best practice: a mailbox on your own domain, e.g. noreply@healhealth.in
    'HH_MAIL_FROM'      => 'digicarelynx@gmail.com',
    'HH_MAIL_FROM_NAME' => 'Heal Health Homeopathy Website',

    // ---- SMTP -------------------------------------------------------------
    // Gmail: create an App Password at https://myaccount.google.com/apppasswords
    // (requires 2-Step Verification). Use that 16-character password here.
    'HH_SMTP_HOST'   => 'smtp.gmail.com',
    'HH_SMTP_USER'   => 'digicarelynx@gmail.com',
    'HH_SMTP_PASS'   => 'your-16-char-app-password',
    'HH_SMTP_PORT'   => '587',
    'HH_SMTP_SECURE' => 'tls',   // 'tls' for 587, 'ssl' for 465
    'HH_SMTP_DEBUG'  => '0',     // 2 while troubleshooting

    // Send the patient an automatic confirmation email as well.
    'HH_PATIENT_COPY' => '1',

    // ---- Instagram --------------------------------------------------------
    // Long-lived access token. Leave blank to use the curated fallback tiles.
    'HH_IG_TOKEN' => '',
];
