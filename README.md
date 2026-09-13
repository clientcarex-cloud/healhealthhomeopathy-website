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

## 4. Instagram reels — no setup required

Reels appear on the site **automatically, with no token, no Meta app and no
OAuth.** The site calls Instagram's own public profile endpoint:

```
GET https://www.instagram.com/api/v1/users/web_profile_info/?username=<handle>
Header: x-ig-app-id: 936619743392459
```

This is the same approach used in production on the Stallion Horse Riding site.
It returns the latest media with thumbnails, play counts, like counts, captions
and the follower count. Nothing to configure — just deploy.

### What the site does with it

- **Reels first.** Video posts are detected via `is_video` and shown ahead of
  photo posts (or exclusively, with `HH_IG_REELS_ONLY`).
- **Thumbnails are mirrored** into `assets/img/ig/<shortcode>.jpg`. fbcdn URLs
  are signed, short-lived and refuse browser hotlinks, so serving them from our
  own domain is what keeps the grid from breaking a few days later. Files are
  validated as real images before being written, and pruned when a post leaves
  the feed.
- **Captions are cleaned** — hashtags and `@mentions` stripped, trimmed to 150
  characters, so the tiles read properly on a clinical site.
- **Cached for an hour** (`HH_IG_CACHE_TTL`). If Instagram is unreachable the
  last good copy keeps serving and the next retry is 15 minutes out, rather than
  hammering the endpoint on every page view.

### Settings

| Setting | Default | Meaning |
|---|---|---|
| `HH_IG_REELS_ONLY`  | `0` | `1` shows reels only and hides photo posts |
| `HH_IG_REELS_FIRST` | `1` | reels at the front, then photos |
| `HH_IG_LIMIT`       | `4` | number of tiles in the single row |
| `HH_IG_CACHE_TTL`   | `3600` | seconds between API calls |

All optional — the defaults work as-is.

### The one caveat

This endpoint is unofficial. Instagram rate-limits it per IP, and some networks
are blocked outright — a blocked server gets `HTTP 401 "Please wait a few
minutes before you try again"`. It works from the Stallion server; whether it
works from this site's host can only be settled by testing from that host.

**To check:** set a password in `includes/config.local.php`

```php
'HH_IG_SETUP_KEY' => 'pick-something-long',
```

then open `https://yourdomain.com/setup-instagram.php` and press
**Test connection**. It reports the exact HTTP status, Instagram's own error
message if any, the follower count and how many reels came back.

### If the server is blocked — OAuth fallback

Only needed if the test fails. The same page can connect the account through
Instagram's official API, which is unaffected by IP blocking:

1. Make the account **Professional** (Instagram app → Settings → Account type
   and tools). Personal accounts cannot use the official API.
2. Create a free app at <https://developers.facebook.com/apps> → **Create app**
   → use case **Other** → type **Business**.
3. Add the **Instagram** product → **API setup with Instagram login**.
4. Under **Business login settings**, add this exact redirect URI:
   `https://yourdomain.com/setup-instagram.php`
5. Put the **Instagram App ID** and **App Secret** in `includes/config.local.php`.
6. Reload the setup page and press **Connect Instagram**.

The redirect URI must match byte for byte, and Instagram requires HTTPS. The
resulting 60-day token then refreshes itself while the site gets traffic.

> Note: the official API does not expose play counts without the Insights
> permission, so reels connected this way show likes but not plays.

### Before the first successful fetch

No made-up posts are shown. Until Instagram has been reached once, the section
shows a single "Follow on Instagram" card linking to the profile, and the
server waits 15 minutes between attempts so page loads stay fast.

## 5. Photos

Doctor photos live in `assets/img/doctor/`. The site serves the `.webp` copies;
the `.png` files are the originals.

| File | Used for |
|------|----------|
| `dr-atiya-cutout-magenta` | Hero portrait (background removed) |
| `dr-atiya-desk` | About section |
| `dr-atiya-avatar-mustard` | Instagram profile avatar |
| `dr-atiya-avatar-pink` | Doctor card in the booking section |
| `dr-atiya-cutout-mustard` | FAQ side card (background removed) |
| `dr-atiya-consultation` | Closing call-to-action band |
| `dr-atiya-portrait-mustard.jpg` | Social share image (`og:image`) |

To replace one, keep the file name and regenerate the WebP, e.g.
`cwebp -q 84 dr-atiya-desk.png -o dr-atiya-desk.webp`.

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
setup-instagram.php        Instagram diagnostics + optional OAuth (password-gated)
api/book.php               Booking endpoint (JSON)
includes/
  config.php               All site content & settings
  config.local.php         Your secrets (git-ignored, you create this)
  config.local.sample.php  Template for the above
  mailer.php               PHPMailer wiring + email templates
  instagram.php            Public feed fetch, reels, thumbnail mirror, OAuth fallback
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
- [ ] Ran **Test connection** at `/setup-instagram.php` from the live server and
      confirmed real reels are showing (this needs no token — see §4)
- [ ] `HH_IG_SETUP_KEY` is a strong password, or `setup-instagram.php` deleted
