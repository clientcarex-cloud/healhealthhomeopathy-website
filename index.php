<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/icons.php';
require_once __DIR__ . '/includes/instagram.php';

$igFeed   = hh_instagram_feed($HH_INSTAGRAM_CFG);
$igPosts  = $igFeed['items'];
$igIsLive = $igFeed['source'] !== 'fallback';

$pageTitle = HH_BRAND . ' by ' . HH_DOCTOR . ' — Homeopathy Clinic in Abids, Hyderabad';
$pageDesc  = 'Dr. Atiya Fatima (BHMS, DHA-licensed) — 20+ years of homeopathic practice, 5,000+ patients across 20+ countries. Treatment for ADHD, depression, infertility, thyroid, skin, allergy and pain. Clinic at MPM Mall, Abids, Hyderabad. Book online or call ' . HH_PHONE_PRETTY . '.';

// Structured data for search engines.
$schema = [
    '@context' => 'https://schema.org',
    '@type'    => 'MedicalClinic',
    'name'     => HH_BRAND . ' by ' . HH_DOCTOR,
    'image'    => 'https://healhealthhomeopathy.com/assets/img/doctor/dr-atiya-portrait-mustard.jpg',
    'description' => $pageDesc,
    'telephone'   => HH_PHONE_E164,
    'email'       => HH_EMAIL_PUBLIC,
    'medicalSpecialty' => 'Homeopathic',
    'priceRange'  => '₹₹',
    'address' => [
        '@type'           => 'PostalAddress',
        'streetAddress'   => HH_ADDRESS_LINE1,
        'addressLocality' => 'Abids, Hyderabad',
        'addressRegion'   => 'Telangana',
        'postalCode'      => '500001',
        'addressCountry'  => 'IN',
    ],
    'hasMap' => HH_MAP_LINK,
    'sameAs' => [HH_INSTAGRAM],
    'openingHoursSpecification' => [
        [
            '@type'     => 'OpeningHoursSpecification',
            'dayOfWeek' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],
            'opens'     => '10:00',
            'closes'    => '19:00',
        ],
    ],
    'employee' => [
        '@type'      => 'Physician',
        'name'       => HH_DOCTOR,
        'image'      => 'https://healhealthhomeopathy.com/assets/img/doctor/dr-atiya-portrait-mustard.jpg',
        'jobTitle'   => 'Homeopathic Physician',
        'medicalSpecialty' => 'Homeopathic',
        'knowsAbout' => array_column(HH_SERVICES, 'title'),
    ],
];

$navLinks = [
    '#services'  => 'Conditions',
    '#about'     => 'About Dr. Atiya',
    '#process'   => 'How it works',
    '#instagram' => 'Instagram',
    '#location'  => 'Clinic',
    '#faq'       => 'FAQs',
];
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?></title>
<meta name="description" content="<?= e($pageDesc) ?>">
<meta name="theme-color" content="#0f5c4d">
<link rel="canonical" href="https://healhealthhomeopathy.com/">

<meta property="og:type" content="website">
<meta property="og:title" content="<?= e($pageTitle) ?>">
<meta property="og:description" content="<?= e($pageDesc) ?>">
<meta property="og:locale" content="en_IN">
<meta property="og:image" content="https://healhealthhomeopathy.com/assets/img/doctor/dr-atiya-portrait-mustard.jpg">
<meta property="og:image:alt" content="<?= e(HH_DOCTOR) ?>, homeopathic physician at Heal Health Homeopathy">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:image" content="https://healhealthhomeopathy.com/assets/img/doctor/dr-atiya-portrait-mustard.jpg">

<link rel="icon" href="assets/img/favicon.svg" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400..700;1,9..144,400..700&family=Inter:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="assets/css/style.css?v=3">
<noscript><style>.reveal { opacity: 1 !important; transform: none !important; }</style></noscript>

<script type="application/ld+json"><?= json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
</head>
<body>

<a class="skip-link" href="#main">Skip to content</a>

<!-- ================= TOP BAR ================= -->
<div class="topbar">
  <div class="container topbar__inner">
    <div class="topbar__loc">
      <?= hh_icon('pin', 15) ?>
      <span><?= e(HH_ADDRESS_LINE1) ?>, <?= e(HH_ADDRESS_LINE2) ?></span>
    </div>
    <div class="topbar__links">
      <span><?= hh_icon('clock', 15) ?> Mon–Sat, 10 AM – 7 PM</span>
      <a href="tel:<?= e(HH_PHONE_E164) ?>"><?= hh_icon('phone', 15) ?> <?= e(HH_PHONE_PRETTY) ?></a>
    </div>
  </div>
</div>

<!-- ================= HEADER ================= -->
<header class="header" id="header">
  <div class="container header__inner">
    <a class="brand" href="#top" aria-label="<?= e(HH_BRAND) ?> home">
      <span class="brand__mark"><?= hh_icon('leaf', 24) ?></span>
      <span class="brand__text">
        <span class="brand__name">Heal Health Homeopathy</span>
        <span class="brand__sub">Dr. Atiya Fatima</span>
      </span>
    </a>

    <nav class="nav" id="nav" aria-label="Main">
      <?php foreach ($navLinks as $href => $label): ?>
        <a href="<?= e($href) ?>"><?= e($label) ?></a>
      <?php endforeach; ?>
      <a class="btn btn--primary" href="#book">Book appointment</a>
    </nav>

    <div class="header__cta">
      <a class="header__phone" href="tel:<?= e(HH_PHONE_E164) ?>">
        <?= hh_icon('phone', 18) ?> <?= e(HH_PHONE_PRETTY) ?>
      </a>
      <a class="btn btn--primary" href="#book" id="headerBook">Book appointment</a>
      <button class="nav-toggle" id="navToggle" aria-expanded="false" aria-controls="nav" aria-label="Open menu">
        <span></span>
      </button>
    </div>
  </div>
</header>

<main id="main">

<!-- ================= HERO ================= -->
<section class="hero" id="top">
  <div class="container hero__grid">
    <div class="hero__copy">
      <span class="hero__badge"><b>BHMS</b> DHA-licensed in Dubai · Practising since 2005</span>

      <h1>Homeopathy that treats the <em>person</em>, not just the report.</h1>

      <p class="hero__lede">
        Dr. Atiya Fatima has spent 20 years listening closely to chronic cases — ADHD, thyroid,
        infertility, skin and gut disorders — and prescribing remedies matched to the whole person.
        Consult in Abids, Hyderabad, or online from anywhere in the world.
      </p>

      <div class="hero__actions">
        <a class="btn btn--primary btn--lg" href="#book"><?= hh_icon('calendar', 18) ?> Book an appointment</a>
        <a class="btn btn--ghost btn--lg" href="tel:<?= e(HH_PHONE_E164) ?>"><?= hh_icon('phone', 18) ?> <?= e(HH_PHONE_PRETTY) ?></a>
      </div>

      <ul class="hero__pills">
        <li class="pill"><?= hh_icon('award', 16) ?> BHMS qualified</li>
        <li class="pill"><?= hh_icon('shield', 16) ?> DHA-licensed, Dubai</li>
        <li class="pill"><?= hh_icon('users', 16) ?> 5,000+ patients treated</li>
        <li class="pill"><?= hh_icon('globe', 16) ?> 20+ countries</li>
      </ul>
    </div>

    <div class="hero__visual">
      <div class="portrait">
        <img src="assets/img/doctor/dr-atiya-cutout-magenta.webp" alt="<?= e(HH_DOCTOR) ?>, homeopathic physician"
             width="409" height="610" fetchpriority="high">

        <div class="portrait__caption">
          <strong><?= e(HH_DOCTOR) ?></strong>
          <span>BHMS · Homeopathic Physician · DHA-licensed</span>
        </div>
      </div>

      <div class="float-card float-card--a">
        <span class="float-card__num">20+</span>
        <span class="float-card__lbl">Years in<br>practice</span>
      </div>
      <div class="float-card float-card--b">
        <span class="float-card__num">5k+</span>
        <span class="float-card__lbl">Patients treated<br>across 20+ countries</span>
      </div>
    </div>
  </div>
</section>

<!-- ================= STATS ================= -->
<section class="stats">
  <div class="container">
    <div class="stats__grid">
      <?php foreach (HH_STATS as [$num, $label, $desc]): ?>
        <div class="stat reveal">
          <div class="stat__num"><?= e($num) ?></div>
          <div class="stat__lbl"><?= e($label) ?></div>
          <p class="stat__desc"><?= e($desc) ?></p>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ================= SERVICES ================= -->
<section class="section section--cream" id="services">
  <div class="container">
    <div class="section-head section-head--center">
      <span class="eyebrow">What we treat</span>
      <h2>Conditions Dr. Atiya consults for</h2>
      <p class="lede">
        Every case starts with a long, unhurried consultation. The remedy follows the person —
        their history, temperament and reports — not a fixed protocol.
      </p>
    </div>

    <div class="services__grid">
      <?php foreach (HH_SERVICES as $slug => $s): ?>
        <article class="service reveal" id="service-<?= e($slug) ?>">
          <div class="service__icon"><?= hh_icon($s['icon'], 26) ?></div>
          <h3><?= e($s['title']) ?></h3>
          <p><?= e($s['blurb']) ?></p>
          <ul class="service__points">
            <?php foreach ($s['points'] as $point): ?>
              <li><?= hh_icon('check', 15) ?> <span><?= e($point) ?></span></li>
            <?php endforeach; ?>
          </ul>
          <a class="service__link" href="#book" data-service="<?= e($slug) ?>">
            Book for this <?= hh_icon('arrow', 15) ?>
          </a>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ================= ABOUT ================= -->
<section class="section section--wash" id="about">
  <div class="container about__grid">
    <div class="about__media reveal">
      <div class="about__frame">
        <img src="assets/img/doctor/dr-atiya-desk.webp" alt="<?= e(HH_DOCTOR) ?> at the consultation desk in the clinic"
             width="408" height="556" loading="lazy">
      </div>
      <div class="about__seal">
        <strong>DHA-licensed</strong>
        <span>Licensed to practise by the Dubai Health Authority.</span>
      </div>
    </div>

    <div>
      <span class="eyebrow">About the doctor</span>
      <h2>Dr. Atiya Fatima, BHMS</h2>
      <p class="lede">
        Two decades of classical homeopathic practice — first in India, then licensed by the
        Dubai Health Authority — treating chronic conditions that had stopped responding elsewhere.
      </p>

      <div class="cred-list">
        <div class="cred">
          <span class="cred__icon"><?= hh_icon('award', 20) ?></span>
          <div>
            <strong>BHMS · 20+ years of clinical practice</strong>
            <p>A Bachelor of Homeopathic Medicine &amp; Surgery with two decades of case-taking across paediatric, hormonal and chronic disease.</p>
          </div>
        </div>
        <div class="cred">
          <span class="cred__icon"><?= hh_icon('shield', 20) ?></span>
          <div>
            <strong>DHA-licensed practitioner in Dubai</strong>
            <p>Cleared the Dubai Health Authority licensing standard — the same benchmark applied to conventional physicians in the UAE.</p>
          </div>
        </div>
        <div class="cred">
          <span class="cred__icon"><?= hh_icon('globe', 20) ?></span>
          <div>
            <strong>5,000+ patients across 20+ countries</strong>
            <p>In-clinic in Hyderabad and online for families in the Gulf, UK, US, Canada, Australia and beyond.</p>
          </div>
        </div>
      </div>

      <div class="hero__actions" style="margin-bottom:0;">
        <a class="btn btn--primary" href="#book">Book a consultation</a>
        <a class="btn btn--ghost" href="<?= e(HH_INSTAGRAM) ?>" target="_blank" rel="noopener noreferrer">
          <?= hh_icon('instagram', 17) ?> Follow on Instagram
        </a>
      </div>
    </div>
  </div>
</section>

<!-- ================= PROCESS ================= -->
<section class="section section--dark" id="process">
  <div class="container">
    <div class="section-head section-head--center">
      <span class="eyebrow">How it works</span>
      <h2>Four steps from first call to follow-up</h2>
      <p class="lede">Homeopathy is slow medicine done carefully. Here is exactly what to expect.</p>
    </div>

    <div class="steps">
      <?php
      $steps = [
          ['Book your slot', 'Pick a date and time on this page, or call us. We confirm by phone within working hours.'],
          ['Detailed case-taking', 'The first consultation runs 40–60 minutes. Bring past reports, prescriptions and scans.'],
          ['Your remedy plan', 'A constitutional remedy plus diet and lifestyle guidance, written for your case alone.'],
          ['Structured follow-up', 'Reviews every 3–4 weeks. Remedies are adjusted as your symptoms and reports change.'],
      ];
      foreach ($steps as $i => [$title, $body]): ?>
        <article class="step reveal">
          <div class="step__num"><?= $i + 1 ?></div>
          <h3><?= e($title) ?></h3>
          <p><?= e($body) ?></p>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ================= INSTAGRAM ================= -->
<section class="section section--cream" id="instagram">
  <div class="container">
    <div class="ig-head">
      <div class="section-head" style="margin-bottom:0;">
        <span class="eyebrow">From the clinic</span>
        <h2><?= $igIsLive && $HH_INSTAGRAM_CFG['reels_only'] ? 'Latest reels' : 'Latest on Instagram' ?></h2>
        <p class="lede">
          Case notes, patient questions answered and clinic updates from Dr. Atiya<?= $igIsLive ? ' — updated automatically from' : ' — follow' ?>
          <a href="<?= e(HH_INSTAGRAM) ?>" target="_blank" rel="noopener noreferrer">@<?= e(HH_IG_HANDLE) ?></a>.
        </p>
      </div>
      <a class="ig-handle" href="<?= e(HH_INSTAGRAM) ?>" target="_blank" rel="noopener noreferrer">
        <span class="ig-handle__avatar">
          <img src="assets/img/doctor/dr-atiya-avatar-mustard.webp" alt="" width="40" height="40" loading="lazy">
          <span class="ig-handle__badge"><?= hh_icon('instagram', 11) ?></span>
        </span>
        <?php if ($igFeed['followers'] > 0): ?>
          Follow · <?= e(hh_ig_short_num($igFeed['followers'])) ?> followers
        <?php else: ?>
          @<?= e(HH_IG_HANDLE) ?>
        <?php endif; ?>
      </a>
    </div>

    <div class="ig-grid">
      <?php foreach ($igPosts as $post): ?>
        <a class="ig-card reveal" href="<?= e($post['permalink']) ?>" target="_blank" rel="noopener noreferrer">
          <div class="ig-card__media">
            <?php if ($post['image'] !== ''): ?>
              <img src="<?= e($post['image']) ?>" alt="<?= e(mb_substr($post['caption'], 0, 90)) ?>"
                   loading="lazy" referrerpolicy="no-referrer" onerror="this.style.display='none'">
              <div class="ig-card__shade"></div>
              <?php if ($post['type'] === 'REEL'): ?>
                <span class="ig-card__play" aria-hidden="true"><?= hh_icon('play', 20) ?></span>
              <?php elseif ($post['type'] === 'CAROUSEL_ALBUM'): ?>
                <span class="ig-card__type"><?= hh_icon('layers', 14) ?></span>
              <?php endif; ?>
              <?php if ($post['caption'] !== ''): ?>
                <p class="ig-card__cap"><?= e($post['caption']) ?></p>
              <?php endif; ?>
            <?php else: ?>
              <div class="ig-card__art">
                <span class="ig-card__topic"><?= e($post['topic'] ?? 'Heal Health') ?></span>
                <p><?= e($post['caption']) ?></p>
              </div>
            <?php endif; ?>
          </div>

          <div class="ig-card__meta">
            <?php if ($post['views'] > 0): ?>
              <span><?= hh_icon('play', 13) ?> <?= e(hh_ig_short_num($post['views'])) ?></span>
            <?php endif; ?>
            <?php if ($post['likes'] > 0): ?>
              <span><?= hh_icon('heart', 13) ?> <?= e(hh_ig_short_num($post['likes'])) ?></span>
            <?php endif; ?>
            <?php if ($post['views'] === 0 && $post['likes'] === 0): ?>
              <span><?= hh_icon('instagram', 13) ?> View on Instagram</span>
            <?php endif; ?>
            <?php $when = hh_instagram_when((int) $post['taken']); ?>
            <?php if ($when !== ''): ?><span class="ig-card__ago"><?= e($when) ?></span><?php endif; ?>
          </div>
        </a>
      <?php endforeach; ?>
    </div>

    <?php if (!$igIsLive): ?>
      <p class="ig-note">
        Showing placeholder tiles — Instagram could not be reached from this server yet.
        <a href="<?= e(HH_INSTAGRAM) ?>" target="_blank" rel="noopener noreferrer">See the live feed &rarr;</a>
      </p>
    <?php endif; ?>
  </div>
</section>

<!-- ================= BOOKING ================= -->
<section class="section section--wash" id="book">
  <div class="container">
    <div class="section-head">
      <span class="eyebrow">Appointments</span>
      <h2>Book your consultation</h2>
      <p class="lede">
        Tell us a little about your concern and pick a preferred time. We will call you on the number
        you give to confirm the exact slot.
      </p>
    </div>

    <div class="booking__grid">
      <!-- Form -->
      <div class="form-card">
        <div class="form-alert" id="formAlert" role="status" aria-live="polite">
          <span id="alertIcon"></span>
          <div><strong id="alertTitle"></strong><span id="alertText"></span></div>
        </div>

        <form id="bookingForm" method="post" action="api/book.php" novalidate>
          <input type="hidden" name="rendered_at" id="renderedAt" value="">
          <div class="hp-field" aria-hidden="true">
            <label for="website">Leave this field empty</label>
            <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
          </div>

          <div class="form-grid">
            <div class="field" data-field="name">
              <label for="f-name">Full name <span class="req">*</span></label>
              <input type="text" id="f-name" name="name" autocomplete="name" placeholder="e.g. Ayesha Khan" required>
              <span class="field__error"></span>
            </div>

            <div class="field" data-field="phone">
              <label for="f-phone">Phone / WhatsApp <span class="req">*</span></label>
              <input type="tel" id="f-phone" name="phone" autocomplete="tel" placeholder="+91 98765 43210" required>
              <span class="field__error"></span>
            </div>

            <div class="field" data-field="email">
              <label for="f-email">Email <span class="hint">(for your confirmation)</span></label>
              <input type="email" id="f-email" name="email" autocomplete="email" placeholder="you@example.com">
              <span class="field__error"></span>
            </div>

            <div class="field" data-field="age">
              <label for="f-age">Patient age</label>
              <input type="number" id="f-age" name="age" min="1" max="120" placeholder="e.g. 34">
              <span class="field__error"></span>
            </div>

            <div class="field" data-field="service">
              <label for="f-service">What would you like help with? <span class="req">*</span></label>
              <select id="f-service" name="service" required>
                <option value="">Select a concern</option>
                <?php foreach (HH_SERVICES as $slug => $s): ?>
                  <option value="<?= e($slug) ?>"><?= e($s['title']) ?></option>
                <?php endforeach; ?>
                <option value="other">Other / general consultation</option>
              </select>
              <span class="field__error"></span>
            </div>

            <div class="field" data-field="mode">
              <label for="f-mode">Consultation type <span class="req">*</span></label>
              <select id="f-mode" name="mode" required>
                <?php foreach (HH_CONSULT_MODES as $value => $label): ?>
                  <option value="<?= e($value) ?>"><?= e($label) ?></option>
                <?php endforeach; ?>
              </select>
              <span class="field__error"></span>
            </div>

            <div class="field" data-field="date">
              <label for="f-date">Preferred date <span class="req">*</span></label>
              <input type="date" id="f-date" name="date" required>
              <span class="field__error"></span>
            </div>

            <div class="field" data-field="slot">
              <label for="f-slot">Preferred time <span class="req">*</span></label>
              <select id="f-slot" name="slot" required>
                <option value="">Select a time slot</option>
                <?php foreach (HH_TIME_SLOTS as $slot): ?>
                  <option value="<?= e($slot) ?>"><?= e($slot) ?></option>
                <?php endforeach; ?>
              </select>
              <span class="field__error"></span>
            </div>

            <div class="field field--full" data-field="message">
              <label for="f-message">Briefly describe your symptoms <span class="hint">(optional, helps us prepare)</span></label>
              <textarea id="f-message" name="message" rows="4"
                placeholder="How long have you had these symptoms? Any current medication, reports or past treatment?"></textarea>
              <span class="field__error"></span>
            </div>
          </div>

          <label class="form-consent">
            <input type="checkbox" id="f-consent" required>
            <span>
              I agree to be contacted by Heal Health Homeopathy on the phone number and email above
              regarding this appointment request.
            </span>
          </label>

          <div class="form-foot">
            <button class="btn btn--primary btn--lg" type="submit" id="submitBtn">
              <span class="btn__label"><?= hh_icon('calendar', 18) ?> Request appointment</span>
            </button>
            <small>We usually confirm within a few working hours.</small>
          </div>
        </form>
      </div>

      <!-- Aside -->
      <aside class="booking-aside">
        <div class="info-card doc-card">
          <img class="doc-card__img" src="assets/img/doctor/dr-atiya-avatar-pink.webp" alt="<?= e(HH_DOCTOR) ?>"
               width="76" height="76" loading="lazy">
          <div>
            <strong><?= e(HH_DOCTOR) ?></strong>
            <span>BHMS · 20+ years in practice</span>
            <p>Your consultation is with Dr. Atiya directly — in clinic or online.</p>
          </div>
        </div>

        <div class="info-card info-card--dark">
          <h3>Prefer to talk?</h3>
          <div class="info-row">
            <span class="info-row__icon"><?= hh_icon('phone', 18) ?></span>
            <div>
              <strong>Call the clinic</strong>
              <a href="tel:<?= e(HH_PHONE_E164) ?>"><?= e(HH_PHONE_PRETTY) ?></a>
            </div>
          </div>
          <div class="info-row">
            <span class="info-row__icon"><?= hh_icon('whatsapp', 18) ?></span>
            <div>
              <strong>WhatsApp</strong>
              <a href="<?= e(HH_WHATSAPP) ?>" target="_blank" rel="noopener noreferrer">Message us directly</a>
            </div>
          </div>
          <div class="info-row">
            <span class="info-row__icon"><?= hh_icon('mail', 18) ?></span>
            <div>
              <strong>Email</strong>
              <a href="mailto:<?= e(HH_EMAIL_PUBLIC) ?>"><?= e(HH_EMAIL_PUBLIC) ?></a>
            </div>
          </div>
        </div>

        <div class="info-card">
          <h3>Clinic hours</h3>
          <?php foreach (HH_CLINIC_HOURS as [$days, $time]): ?>
            <div class="hours-row" style="border-bottom-color:var(--ink-100);">
              <span style="color:var(--ink-500);"><?= e($days) ?></span>
              <span style="color:var(--ink-900);"><?= e($time) ?></span>
            </div>
          <?php endforeach; ?>
          <p style="margin:16px 0 0;font-size:13.5px;color:var(--ink-400);">
            Online consultations are scheduled to suit international time zones.
          </p>
        </div>

        <div class="info-card">
          <h3>What to bring</h3>
          <ul class="service__points">
            <li><?= hh_icon('check', 15) ?> <span>Previous prescriptions and reports</span></li>
            <li><?= hh_icon('check', 15) ?> <span>A list of current medication</span></li>
            <li><?= hh_icon('check', 15) ?> <span>Notes on when symptoms worsen</span></li>
          </ul>
        </div>
      </aside>
    </div>
  </div>
</section>

<!-- ================= LOCATION ================= -->
<section class="section section--cream" id="location">
  <div class="container">
    <div class="map__grid reveal">
      <div class="map__info">
        <span class="eyebrow">Visit us</span>
        <h2>The clinic in Abids</h2>

        <div class="addr-block">
          <div class="addr-row">
            <span class="addr-row__icon"><?= hh_icon('pin', 18) ?></span>
            <div>
              <strong>Address</strong>
              <span><?= e(HH_ADDRESS_LINE1) ?><br><?= e(HH_ADDRESS_LINE2) ?></span>
            </div>
          </div>
          <div class="addr-row">
            <span class="addr-row__icon"><?= hh_icon('phone', 18) ?></span>
            <div>
              <strong>Phone</strong>
              <a href="tel:<?= e(HH_PHONE_E164) ?>"><?= e(HH_PHONE_PRETTY) ?></a>
            </div>
          </div>
          <div class="addr-row">
            <span class="addr-row__icon"><?= hh_icon('clock', 18) ?></span>
            <div>
              <strong>Hours</strong>
              <span>Monday – Saturday, 10:00 AM – 7:00 PM<br>Sunday by appointment</span>
            </div>
          </div>
        </div>

        <div class="hero__actions" style="margin-bottom:0;">
          <a class="btn btn--primary" href="<?= e(HH_MAP_LINK) ?>" target="_blank" rel="noopener noreferrer">
            <?= hh_icon('pin', 17) ?> Get directions
          </a>
          <a class="btn btn--ghost" href="#book">Book a visit</a>
        </div>
      </div>

      <div class="map__embed">
        <iframe
          src="<?= e(HH_MAP_EMBED) ?>"
          title="Map showing Heal Health Homeopathy at MPM Mall, Hanuman Tekdi, Abids, Hyderabad"
          loading="lazy"
          referrerpolicy="no-referrer-when-downgrade"
          allowfullscreen></iframe>
      </div>
    </div>
  </div>
</section>

<!-- ================= FAQ ================= -->
<section class="section section--wash" id="faq">
  <div class="container">
    <div class="section-head section-head--center">
      <span class="eyebrow">Questions</span>
      <h2>Before you book</h2>
    </div>

    <div class="faq-wrap">
    <div class="faq">
      <?php
      $faqs = [
          ['How long does homeopathic treatment take to work?',
           'It depends on how long the condition has been present. Acute complaints often respond within days. Chronic conditions such as thyroid disorders, psoriasis or PCOS usually need three to six months of consistent treatment with regular follow-ups.'],
          ['Can I continue my current allopathic medication?',
           'Yes. Never stop prescribed medication on your own. Dr. Atiya works alongside your existing treatment and, where your reports support it, will coordinate a gradual reduction with your treating physician.'],
          ['Do you consult online for patients outside India?',
           'Yes. Patients in over 20 countries consult online by video. Case-taking, prescriptions and follow-ups all happen remotely, and remedies can be sourced locally or couriered where permitted.'],
          ['What happens in the first consultation?',
           'The first consultation runs 40 to 60 minutes. Dr. Atiya takes a detailed history — symptoms, medical background, sleep, appetite, temperament and family history — then studies your reports before prescribing.'],
          ['Are homeopathic remedies safe for children?',
           'Yes. Remedies are given in minute doses and are widely used in paediatric practice, including for ADHD, recurrent infections and allergies. Dosage is always adjusted for the child.'],
          ['How do I pay, and what does it cost?',
           'Consultation fees are shared when we call to confirm your appointment, and vary between first consultations and follow-ups, and between in-clinic and online. Payment is accepted at the clinic or by online transfer.'],
      ];
      foreach ($faqs as $i => [$q, $a]): ?>
        <details class="faq__item"<?= $i === 0 ? ' open' : '' ?>>
          <summary class="faq__q"><?= e($q) ?></summary>
          <div class="faq__a"><p><?= e($a) ?></p></div>
        </details>
      <?php endforeach; ?>
    </div>

    <aside class="faq-doc reveal">
      <div class="faq-doc__media">
        <img src="assets/img/doctor/dr-atiya-cutout-mustard.webp" alt="<?= e(HH_DOCTOR) ?>"
             width="440" height="567" loading="lazy">
      </div>
      <div class="faq-doc__body">
        <strong>Still have a question?</strong>
        <p>Message the clinic on WhatsApp and we will answer before you book.</p>
        <a class="btn btn--primary btn--block" href="<?= e(HH_WHATSAPP) ?>" target="_blank" rel="noopener noreferrer">
          <?= hh_icon('whatsapp', 17) ?> Ask on WhatsApp
        </a>
      </div>
    </aside>
    </div>
  </div>
</section>

<!-- ================= CTA ================= -->
<section class="section section--cream" style="padding-top:0;">
  <div class="container">
    <div class="cta-band reveal">
      <div class="cta-band__copy">
        <h2>Start with one honest conversation</h2>
        <p>
          Twenty years of practice, 5,000+ patients, 20+ countries — and every case still begins
          with listening. Book a consultation with Dr. Atiya Fatima today.
        </p>
        <div class="cta-band__actions">
          <a class="btn btn--gold btn--lg" href="#book"><?= hh_icon('calendar', 18) ?> Book appointment</a>
          <a class="btn btn--light btn--lg" href="tel:<?= e(HH_PHONE_E164) ?>"><?= hh_icon('phone', 18) ?> <?= e(HH_PHONE_PRETTY) ?></a>
        </div>
      </div>
      <div class="cta-band__media">
        <img src="assets/img/doctor/dr-atiya-consultation.webp" alt="<?= e(HH_DOCTOR) ?> in consultation with a patient at the clinic"
             width="507" height="556" loading="lazy">
      </div>
    </div>
  </div>
</section>

</main>

<!-- ================= FOOTER ================= -->
<footer class="footer">
  <div class="container">
    <div class="footer__grid">
      <div>
        <div class="brand" style="margin-bottom:18px;">
          <span class="brand__mark"><?= hh_icon('leaf', 24) ?></span>
          <span class="brand__text">
            <span class="brand__name">Heal Health Homeopathy</span>
            <span class="brand__sub">Dr. Atiya Fatima</span>
          </span>
        </div>
        <p>
          Classical homeopathic care for chronic and acute conditions — in Abids, Hyderabad,
          and online worldwide. BHMS qualified, DHA-licensed in Dubai.
        </p>
        <div class="footer__social">
          <a href="<?= e(HH_INSTAGRAM) ?>" target="_blank" rel="noopener noreferrer" aria-label="Instagram"><?= hh_icon('instagram', 19) ?></a>
          <a href="<?= e(HH_WHATSAPP) ?>" target="_blank" rel="noopener noreferrer" aria-label="WhatsApp"><?= hh_icon('whatsapp', 19) ?></a>
          <a href="tel:<?= e(HH_PHONE_E164) ?>" aria-label="Call the clinic"><?= hh_icon('phone', 19) ?></a>
          <a href="mailto:<?= e(HH_EMAIL_PUBLIC) ?>" aria-label="Email the clinic"><?= hh_icon('mail', 19) ?></a>
        </div>
      </div>

      <div>
        <h4>Conditions</h4>
        <div class="footer__links">
          <?php foreach (array_slice(HH_SERVICES, 0, 5, true) as $slug => $s): ?>
            <a href="#service-<?= e($slug) ?>"><?= e($s['title']) ?></a>
          <?php endforeach; ?>
        </div>
      </div>

      <div>
        <h4>More</h4>
        <div class="footer__links">
          <?php foreach (array_slice(HH_SERVICES, 5, 5, true) as $slug => $s): ?>
            <a href="#service-<?= e($slug) ?>"><?= e($s['title']) ?></a>
          <?php endforeach; ?>
          <a href="#faq">FAQs</a>
        </div>
      </div>

      <div>
        <h4>Clinic</h4>
        <div class="footer__links">
          <a href="<?= e(HH_MAP_LINK) ?>" target="_blank" rel="noopener noreferrer">
            <?= e(HH_ADDRESS_LINE1) ?><br><?= e(HH_ADDRESS_LINE2) ?>
          </a>
          <a href="tel:<?= e(HH_PHONE_E164) ?>"><?= e(HH_PHONE_PRETTY) ?></a>
          <a href="mailto:<?= e(HH_EMAIL_PUBLIC) ?>"><?= e(HH_EMAIL_PUBLIC) ?></a>
          <span style="font-size:14.5px;">Mon–Sat · 10:00 AM – 7:00 PM</span>
        </div>
      </div>
    </div>

    <p class="disclaimer">
      <strong>Medical disclaimer:</strong> The content on this website is for general information only
      and is not a substitute for an in-person medical consultation, diagnosis or treatment. Never stop
      prescribed medication without speaking to your treating doctor. In a medical emergency, contact
      your nearest hospital immediately.
    </p>

    <div class="footer__bottom">
      <p>&copy; <?= date('Y') ?> Heal Health Homeopathy. All rights reserved.</p>
      <p>Dr. Atiya Fatima, BHMS · DHA-licensed, Dubai</p>
      <p class="footer__credit">Developed by <a href="https://clientcarex.com/" target="_blank" rel="noopener">ClientCareX</a></p>
    </div>
  </div>
</footer>

<!-- Floating actions -->
<a class="wa-float" href="<?= e(HH_WHATSAPP) ?>" target="_blank" rel="noopener noreferrer" aria-label="Chat on WhatsApp">
  <?= hh_icon('whatsapp', 26) ?>
</a>

<nav class="action-bar" aria-label="Quick actions">
  <a href="tel:<?= e(HH_PHONE_E164) ?>"><?= hh_icon('phone', 19) ?> Call</a>
  <a href="<?= e(HH_WHATSAPP) ?>" target="_blank" rel="noopener noreferrer"><?= hh_icon('whatsapp', 19) ?> WhatsApp</a>
  <a href="<?= e(HH_MAP_LINK) ?>" target="_blank" rel="noopener noreferrer"><?= hh_icon('pin', 19) ?> Directions</a>
  <a class="is-primary" href="#book"><?= hh_icon('calendar', 19) ?> Book</a>
</nav>

<script src="assets/js/main.js?v=1" defer></script>
</body>
</html>
