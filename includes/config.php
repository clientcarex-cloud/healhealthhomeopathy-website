<?php
/**
 * Heal Health Homeopathy — central configuration.
 *
 * Secrets (SMTP password, Instagram token) are NOT stored here.
 * Put them in config.local.php (git-ignored) or real environment variables.
 * See config.local.sample.php for the template.
 */

declare(strict_types=1);

// ---------------------------------------------------------------------------
// Load local secrets, if present
// ---------------------------------------------------------------------------
$localConfig = [];
if (is_file(__DIR__ . '/config.local.php')) {
    $loaded = require __DIR__ . '/config.local.php';
    if (is_array($loaded)) {
        $localConfig = $loaded;
    }
}

/**
 * Read a setting: config.local.php first, then environment, then default.
 */
function hh_setting(string $key, string $default = ''): string
{
    global $localConfig;

    if (isset($localConfig[$key]) && $localConfig[$key] !== '') {
        return (string) $localConfig[$key];
    }

    $env = getenv($key);
    if ($env !== false && $env !== '') {
        return (string) $env;
    }

    return $default;
}

// ---------------------------------------------------------------------------
// Practice details
// ---------------------------------------------------------------------------
const HH_BRAND        = 'Heal Health Homeopathy';
const HH_DOCTOR       = 'Dr. Atiya Fatima';
const HH_TAGLINE      = 'Gentle homeopathic care, rooted in 20 years of clinical practice.';
const HH_PHONE_RAW    = '7032258110';
const HH_PHONE_E164   = '+917032258110';
const HH_PHONE_PRETTY = '+91 70322 58110';
const HH_EMAIL_PUBLIC = 'digicarelynx@gmail.com';

const HH_ADDRESS_LINE1 = 'Level 4, MPM Mall, Hanuman Tekdi';
const HH_ADDRESS_LINE2 = 'Abids, Hyderabad, Telangana 500001';
const HH_ADDRESS_FULL  = 'Level 4, MPM Mall, Hanuman Tekdi, Abids, Hyderabad, Telangana 500001';

const HH_MAP_LINK  = 'https://maps.app.goo.gl/vZPqEZuTTw1gf29q9';
const HH_MAP_EMBED = 'https://www.google.com/maps?q=MPM+Mall,+Hanuman+Tekdi,+Abids,+Hyderabad,+Telangana+500001&output=embed';

const HH_INSTAGRAM  = 'https://www.instagram.com/dr.atiya.healhealthhomeopathy/';
const HH_IG_HANDLE  = 'dr.atiya.healhealthhomeopathy';
const HH_WHATSAPP   = 'https://wa.me/917032258110';

const HH_CLINIC_HOURS = [
    ['Monday – Saturday', '10:00 AM – 7:00 PM'],
    ['Sunday',            'By appointment only'],
];

// ---------------------------------------------------------------------------
// Trust markers
// ---------------------------------------------------------------------------
const HH_STATS = [
    ['20+',    'Years of practice',      'Two decades of classical homeopathic case-taking.'],
    ['5,000+', 'Patients treated',       'Chronic and acute cases seen through to resolution.'],
    ['20+',    'Countries served',       'In-clinic in Hyderabad, online across the world.'],
    ['DHA',    'Licensed in Dubai',      'Dubai Health Authority licensed practitioner.'],
];

// ---------------------------------------------------------------------------
// Services (slug => details). Slugs feed the booking form dropdown.
// ---------------------------------------------------------------------------
const HH_SERVICES = [
    'adhd' => [
        'title' => 'ADHD & Child Behaviour',
        'icon'  => 'brain',
        'blurb' => 'Focus, restlessness, impulsivity and learning difficulty in children and adults — addressed constitutionally, without sedation.',
        'points'=> ['Attention & concentration support', 'Behavioural and mood balance', 'Parent counselling included'],
    ],
    'depression' => [
        'title' => 'Depression & Anxiety',
        'icon'  => 'heart-pulse',
        'blurb' => 'Low mood, panic, sleeplessness and chronic stress treated with remedies matched to your individual emotional picture.',
        'points'=> ['Non-habit forming remedies', 'Sleep & appetite restoration', 'Confidential, unhurried consults'],
    ],
    'infertility' => [
        'title' => 'Infertility — Male & Female',
        'icon'  => 'seedling',
        'blurb' => 'PCOS, hormonal imbalance, low sperm count and unexplained infertility managed alongside your fertility workup.',
        'points'=> ['PCOS & cycle regulation', 'Sperm count & motility support', 'Pre-conception care for couples'],
    ],
    'gastric' => [
        'title' => 'Gastric Care',
        'icon'  => 'stomach',
        'blurb' => 'Acidity, IBS, bloating, constipation and chronic gastritis — treating the gut, not just the symptom.',
        'points'=> ['Acid reflux & hyperacidity', 'IBS and irregular bowels', 'Diet guidance with every plan'],
    ],
    'hairfall' => [
        'title' => 'Hairfall Solution',
        'icon'  => 'strand',
        'blurb' => 'Hair thinning, alopecia areata, dandruff and post-illness hair loss treated from the root cause outward.',
        'points'=> ['Alopecia & patchy hair loss', 'Thyroid & PCOS related shedding', 'Scalp health restoration'],
    ],
    'pain' => [
        'title' => 'Pain Management',
        'icon'  => 'joint',
        'blurb' => 'Arthritis, cervical and lumbar spondylosis, migraine and sciatica managed without long-term painkillers.',
        'points'=> ['Joint & back pain relief', 'Migraine frequency reduction', 'Mobility and stiffness support'],
    ],
    'skin' => [
        'title' => 'Skin Care',
        'icon'  => 'skin',
        'blurb' => 'Eczema, psoriasis, acne, vitiligo and chronic urticaria — deep-acting treatment for lasting clearance.',
        'points'=> ['Acne & pigmentation', 'Psoriasis & eczema', 'Vitiligo and urticaria'],
    ],
    'allergy' => [
        'title' => 'Allergy Care',
        'icon'  => 'allergy',
        'blurb' => 'Allergic rhinitis, asthma, sinusitis and food sensitivities treated by rebuilding immune tolerance.',
        'points'=> ['Dust & seasonal allergies', 'Asthma & recurrent wheeze', 'Chronic sinusitis relief'],
    ],
    'thyroid' => [
        'title' => 'Thyro Care',
        'icon'  => 'thyroid',
        'blurb' => 'Hypothyroidism, hyperthyroidism and thyroiditis managed with monitoring alongside your reports.',
        'points'=> ['Hypo & hyper thyroid support', 'Weight and energy balance', 'Report-based follow-ups'],
    ],
];

// ---------------------------------------------------------------------------
// Booking form options
// ---------------------------------------------------------------------------
const HH_TIME_SLOTS = [
    '10:00 AM – 11:00 AM',
    '11:00 AM – 12:00 PM',
    '12:00 PM – 01:00 PM',
    '04:00 PM – 05:00 PM',
    '05:00 PM – 06:00 PM',
    '06:00 PM – 07:00 PM',
];

const HH_CONSULT_MODES = [
    'clinic' => 'In-clinic — Abids, Hyderabad',
    'online' => 'Online video consultation',
];

// ---------------------------------------------------------------------------
// Mail delivery
// ---------------------------------------------------------------------------
$HH_MAIL = [
    // Where every booking request lands.
    'to_email'    => hh_setting('HH_MAIL_TO', 'digicarelynx@gmail.com'),
    'to_name'     => hh_setting('HH_MAIL_TO_NAME', 'Heal Health Homeopathy'),

    // Envelope sender. On shared hosting this must be a mailbox on your domain.
    'from_email'  => hh_setting('HH_MAIL_FROM', 'digicarelynx@gmail.com'),
    'from_name'   => hh_setting('HH_MAIL_FROM_NAME', 'Heal Health Homeopathy Website'),

    // SMTP. Leave HH_SMTP_HOST empty to fall back to PHP mail().
    'smtp_host'   => hh_setting('HH_SMTP_HOST', ''),
    'smtp_user'   => hh_setting('HH_SMTP_USER', ''),
    'smtp_pass'   => hh_setting('HH_SMTP_PASS', ''),
    'smtp_port'   => (int) hh_setting('HH_SMTP_PORT', '587'),
    'smtp_secure' => hh_setting('HH_SMTP_SECURE', 'tls'), // tls | ssl | ''
    'smtp_debug'  => (int) hh_setting('HH_SMTP_DEBUG', '0'),

    // Send the patient a confirmation copy.
    'send_patient_copy' => hh_setting('HH_PATIENT_COPY', '1') === '1',
];

// ---------------------------------------------------------------------------
// Instagram feed
// ---------------------------------------------------------------------------
$HH_INSTAGRAM_CFG = [
    // Meta app credentials — needed once, to connect the account via
    // /setup-instagram.php. After that the token lives in storage/ and
    // refreshes itself.
    'app_id'       => hh_setting('HH_IG_APP_ID', ''),
    'app_secret'   => hh_setting('HH_IG_APP_SECRET', ''),

    // Password for /setup-instagram.php. Blank => the page refuses to run.
    'setup_key'    => hh_setting('HH_IG_SETUP_KEY', ''),

    // Optional: paste a long-lived token directly instead of using OAuth.
    // A token connected through the setup page takes precedence over this.
    'access_token' => hh_setting('HH_IG_TOKEN', ''),

    // Reels handling.
    'reels_only'   => hh_setting('HH_IG_REELS_ONLY', '0') === '1',
    'reels_first'  => hh_setting('HH_IG_REELS_FIRST', '1') === '1',

    'limit'        => (int) hh_setting('HH_IG_LIMIT', '6'),
    'cache_ttl'    => (int) hh_setting('HH_IG_CACHE_TTL', '3600'), // seconds
    'cache_file'   => dirname(__DIR__) . '/storage/cache/instagram.json',
];

// ---------------------------------------------------------------------------
// Paths
// ---------------------------------------------------------------------------
const HH_BOOKINGS_DIR = __DIR__ . '/../storage/bookings';

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
