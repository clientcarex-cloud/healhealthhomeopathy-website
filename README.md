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

## 4. Connect Instagram (for real reels)

**Instagram publishes nothing without authentication.** The public profile page
is a JavaScript shell with no post data in it, and `i.instagram.com` answers
`require_login`. Scraping is not an option — the account has to be connected
once through Instagram's own OAuth. After that it runs itself.

The site ships a guided page that does the whole exchange for you:

### Step 1 — make the account Professional

In the Instagram app: **Settings → Account type and tools → Switch to
professional account**. Creator is fine. Personal accounts cannot use this API.

### Step 2 — set a setup password

In `includes/config.local.php`:

```php
'HH_IG_SETUP_KEY' => 'pick-something-long',
```

### Step 3 — create a free Meta app

1. Go to <https://developers.facebook.com/apps> → **Create app**
2. Use case: **Other** → type: **Business**
3. Add the **Instagram** product → **API setup with Instagram login**
4. Under **Business login settings**, add this exact redirect URI:
   `https://yourdomain.com/setup-instagram.php`
5. Copy the **Instagram App ID** and **App Secret** into `includes/config.local.php`:

```php
'HH_IG_APP_ID'     => '...',
'HH_IG_APP_SECRET' => '...',
```

### Step 4 — click Connect

Open `https://yourdomain.com/setup-instagram.php`, enter your setup password,
and press **Connect Instagram**. That page then shows connection status, days
until expiry, a thumbnail preview of what the site is showing, and buttons to
fetch now, refresh the token or disconnect.

> The redirect URI must match **byte for byte**, and Instagram requires
> **HTTPS** — this will not work over plain `http://` on a real domain.

### What happens after connecting

- Posts are fetched from `graph.instagram.com/me/media` and cached for an hour
- **Thumbnails are mirrored into `assets/img/ig/`** — Instagram's CDN URLs are
  signed and expire after a few days, so hotlinking them means broken images later
- **The 60-day token refreshes itself** once it is within 10 days of expiring,
  as long as the site gets traffic. Nothing to renew by hand.
- If the API is ever unreachable, the site serves the last good cache, and only
  falls back to placeholder tiles if it has never successfully fetched

### Reels settings

| Setting | Default | Meaning |
|---|---|---|
| `HH_IG_REELS_ONLY`  | `0` | `1` shows reels only and hides photo posts |
| `HH_IG_REELS_FIRST` | `1` | reels at the front, then photos |
| `HH_IG_LIMIT`       | `6` | number of tiles in the grid |
| `HH_IG_CACHE_TTL`   | `3600` | seconds between API calls |

### If the account is left idle

A long-lived token expires 60 days after its last refresh. The auto-refresh
keeps it alive on any site with normal traffic. If the site sits idle for two
months, just open `setup-instagram.php` and press **Connect Instagram** again.

### Editing the placeholder tiles

They only appear before the account is connected. Change them in
`HH_IG_FALLBACK` at the top of `includes/instagram.php`.

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
setup-instagram.php        Guided Instagram connection (password-gated)
api/book.php               Booking endpoint (JSON)
includes/
  config.php               All site content & settings
  config.local.php         Your secrets (git-ignored, you create this)
  config.local.sample.php  Template for the above
  mailer.php               PHPMailer wiring + email templates
  instagram.php            Instagram OAuth, media fetch, thumbnail mirror
  icons.php                Inline SVG icon set
assets/css/style.css       Styles
assets/js/main.js          Nav, scroll reveal, form handling
vendor/PHPMailer/          Bundled PHPMailer 6.9.3
storage/bookings/          Booking log (JSONL)
storage/cache/             Instagram cache + rate-limit counters
storage/instagram_token.json   Long-lived token (chmod 600, git-ignored)
assets/img/ig/             Mirrored Instagram thumbnails
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
- [ ] Instagram connected at `/setup-instagram.php` and showing real reels
- [ ] `HH_IG_SETUP_KEY` is a strong password (that page can reconnect the account)
