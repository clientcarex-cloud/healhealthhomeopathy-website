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
    // Instagram gives no public access to posts, so the account must be
    // connected once via OAuth. Create a Meta app (Instagram API with
    // Instagram Login), then open /setup-instagram.php and click Connect.
    // Full walkthrough in README section 4.
    'HH_IG_APP_ID'     => '',
    'HH_IG_APP_SECRET' => '',

    // Pick your own password — /setup-instagram.php will ask for it.
    // Leave blank and that page refuses to run at all.
    'HH_IG_SETUP_KEY'  => '',

    // Reels behaviour
    'HH_IG_REELS_ONLY'  => '0',  // '1' = show reels only, hide photo posts
    'HH_IG_REELS_FIRST' => '1',  // '1' = reels at the front, then photos
    'HH_IG_LIMIT'       => '4',  // tiles in the single row
    'HH_IG_CACHE_TTL'   => '3600',

    // Alternative to the setup page: paste a long-lived token here.
    'HH_IG_TOKEN' => '',
];
