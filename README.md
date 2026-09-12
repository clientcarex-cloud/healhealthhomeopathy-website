# Heal Health Homeopathy — Dr. Atiya Fatima

Marketing site + appointment booking system for the homeopathy practice of
**Dr. Atiya Fatima** (BHMS, DHA-licensed), MPM Mall, Abids, Hyderabad.

Plain PHP — no build step, no framework, no Composer install required.

---

## 1. Quick start (local)

```bash
php -S localhost:8787 -t .
```

Open <http://localhost:8787>.

## 2. Deploy

Upload the whole folder to any PHP 8.1+ host (Hostinger, cPanel, etc.) and point
the domain at it. Then do the two configuration steps below.

**Make sure `storage/` is writable:**

```bash
chmod -R 775 storage
```

> On Apache, `storage/`, `includes/` and `vendor/` are already blocked from the
> web by their own `.htaccess`. On **nginx** there is no `.htaccess` — add this
> to your server block:
>
> ```nginx
> location ~ ^/(storage|includes|vendor)/ { deny all; return 404; }
> ```

---

## 3. Configure email (required)

Bookings are emailed with **PHPMailer** (bundled in `vendor/PHPMailer`, v6.9.3).

```bash
cp includes/config.local.sample.php includes/config.local.php
```

Edit `includes/config.local.php` and fill in your SMTP credentials.
This file is git-ignored — **never commit it**.

### Using Gmail (digicarelynx@gmail.com)

Gmail rejects your normal account password from scripts. You need an **App Password**:

1. Turn on 2-Step Verification: <https://myaccount.google.com/security>
2. Create an App Password: <https://myaccount.google.com/apppasswords>
3. Paste the 16-character password into `HH_SMTP_PASS`.

```php
'HH_SMTP_HOST'   => 'smtp.gmail.com',
'HH_SMTP_USER'   => 'digicarelynx@gmail.com',
'HH_SMTP_PASS'   => 'xxxx xxxx xxxx xxxx',   // App Password
'HH_SMTP_PORT'   => '587',
'HH_SMTP_SECURE' => 'tls',
```

### A note on deliverability

Gmail will rewrite the visible sender to your Gmail address regardless of what
you set. Once you have a domain mailbox (e.g. `noreply@healhealth.in`), switch
`HH_SMTP_*` and `HH_MAIL_FROM` to it and add SPF/DKIM records — bookings will
then land in the inbox instead of spam far more reliably.

`HH_MAIL_TO` stays `digicarelynx@gmail.com`, so that is where bookings arrive.

### If SMTP is left blank

The form falls back to PHP's `mail()`. It works on many shared hosts but is
much more likely to be filtered as spam. SMTP is strongly preferred.

### Troubleshooting

Set `'HH_SMTP_DEBUG' => '2'` in `config.local.php` and watch your PHP error log —
PHPMailer writes the full SMTP conversation there. Set it back to `'0'` afterwards.

---

## 4. Configure the Instagram feed (optional)

Out of the box the Instagram section shows six **curated tiles** that link to
the profile — it looks intentional and never appears broken. To pull real posts:

1. Create a Meta app and connect the Instagram account
   `dr.atiya.healhealthhomeopathy` via **Instagram Basic Display** /
   **Instagram Graph API**.
2. Generate a **long-lived access token** (valid 60 days, refreshable).
3. Put it in `config.local.php`:
   ```php
   'HH_IG_TOKEN' => 'IGQVJ...',
   ```

Posts are then fetched live and cached for 1 hour in
`storage/cache/instagram.json`. If the API is unreachable the site serves the
stale cache, then the curated tiles — so the section degrades gracefully.

Long-lived tokens expire after 60 days. Either refresh it on a schedule or plan
to paste a new one every couple of months.

To edit the curated tiles, change `HH_IG_FALLBACK` in `includes/instagram.php`.

---

## 5. Add real photos

Two images are picked up automatically if present — the page falls back to a
designed placeholder panel when they are missing:

| File | Used for | Suggested size |
|------|----------|----------------|
| `assets/img/dr-atiya.jpg` | Hero portrait | 640 × 740, portrait |
| `assets/img/clinic.jpg`   | About section | 560 × 640, portrait |

---

## 6. Editing content

Almost all copy lives in **`includes/config.php`** — no HTML editing needed:

| What | Where |
|------|-------|
| Phone, email, address, map link | `HH_PHONE_*`, `HH_EMAIL_PUBLIC`, `HH_ADDRESS_*`, `HH_MAP_*` |
| The 9 conditions treated | `HH_SERVICES` |
| Stat strip (20+, 5,000+, …) | `HH_STATS` |
| Booking time slots | `HH_TIME_SLOTS` |
| In-clinic / online options | `HH_CONSULT_MODES` |
| Clinic hours | `HH_CLINIC_HOURS` |

FAQs and the four "how it works" steps are arrays near the bottom of `index.php`.

Adding a service to `HH_SERVICES` automatically adds it to the services grid,
the booking dropdown and the footer — pick an `icon` name from
`includes/icons.php`.

---

## 7. Where bookings go

Every submission is **written to disk before the email is attempted**, so a mail
outage never loses a lead:

```
storage/bookings/YYYY-MM.jsonl     # one JSON object per line
```

Each booking also gets a reference like `HH-8FE9EC`, shown to the patient and
included in both emails.

Two emails go out per booking:
1. **To the clinic** (`digicarelynx@gmail.com`) — full details, with
   *Reply-To* set to the patient plus one-tap call and WhatsApp buttons.
2. **To the patient** — an acknowledgement with clinic address and directions.
   Turn this off with `'HH_PATIENT_COPY' => '0'`.

### Spam protection

- Hidden honeypot field (bots fill it, humans never see it)
- Time trap — submissions faster than 3 seconds are rejected
- Per-IP rate limit — 5 submissions per hour
- Full server-side validation; the browser checks are a convenience only

---

## 8. Project layout

```
index.php                  Single-page site
api/book.php               Booking endpoint (JSON)
includes/
  config.php               All site content & settings
  config.local.php         Your secrets (git-ignored, you create this)
  config.local.sample.php  Template for the above
  mailer.php               PHPMailer wiring + email templates
  instagram.php            Instagram Graph API + cache + fallback
  icons.php                Inline SVG icon set
assets/css/style.css       Styles
assets/js/main.js          Nav, scroll reveal, form handling
vendor/PHPMailer/          Bundled PHPMailer 6.9.3
storage/bookings/          Booking log (JSONL)
storage/cache/             Instagram cache + rate-limit counters
```

---

## 9. Pre-launch checklist

- [ ] `includes/config.local.php` created with working SMTP credentials
- [ ] Submitted a real test booking and confirmed it arrived at `digicarelynx@gmail.com`
- [ ] `storage/` is writable (`chmod -R 775 storage`)
- [ ] `storage/`, `includes/`, `vendor/` are not reachable over the web
- [ ] Real photos added at `assets/img/dr-atiya.jpg` and `clinic.jpg`
- [ ] HTTPS enabled
- [ ] `canonical` URL in `index.php` updated to the real domain
- [ ] Clinic hours and time slots match actual practice hours
