<?php
/**
 * Instagram diagnostics + optional OAuth fallback.
 *
 * The site does NOT need this page: reels are fetched from Instagram's public
 * profile endpoint with no credentials. Use this page to
 *   - check whether this server can reach that endpoint, and
 *   - connect the account through OAuth if the server's IP is ever blocked.
 *
 * Protected by HH_IG_SETUP_KEY in includes/config.local.php.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/icons.php';
require_once __DIR__ . '/includes/instagram.php';

session_start();

$cfg       = $HH_INSTAGRAM_CFG;
$setupKey  = $cfg['setup_key'];
$appId     = $cfg['app_id'];
$appSecret = $cfg['app_secret'];

// The redirect URI must match the Meta app settings byte for byte.
$scheme      = (($_SERVER['HTTPS'] ?? '') === 'on' || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') ? 'https' : 'http';
$host        = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
$redirectUri = $scheme . '://' . $host . strtok((string) $_SERVER['REQUEST_URI'], '?');

$notice = null;   // ['kind' => 'ok'|'error'|'info', 'title' => ..., 'text' => ...]
$probe  = null;   // ['ok' => bool, 'text' => ...]

// ---------------------------------------------------------------------------
// Gate
// ---------------------------------------------------------------------------
if ($setupKey === '') {
    http_response_code(503);
    $notice  = ['kind' => 'error', 'title' => 'Setup is disabled',
                'text'  => 'Set HH_IG_SETUP_KEY in includes/config.local.php to a password of your choice, then reload this page.'];
    $blocked = true;
} else {
    $blocked = false;

    if (($_POST['action'] ?? '') === 'login' && hash_equals($setupKey, (string) ($_POST['key'] ?? ''))) {
        $_SESSION['hh_ig_setup'] = true;
    }
    if (($_GET['logout'] ?? '') === '1') {
        unset($_SESSION['hh_ig_setup']);
    }
    if (($_POST['action'] ?? '') === 'login' && empty($_SESSION['hh_ig_setup'])) {
        $notice = ['kind' => 'error', 'title' => 'Wrong password', 'text' => 'That does not match HH_IG_SETUP_KEY.'];
    }
}

$authed = !$blocked && !empty($_SESSION['hh_ig_setup']);

// ---------------------------------------------------------------------------
// Actions
// ---------------------------------------------------------------------------
if ($authed) {

    // OAuth callback.
    if (isset($_GET['code'])) {
        if (!hash_equals((string) ($_SESSION['hh_ig_state'] ?? '_'), (string) ($_GET['state'] ?? ''))) {
            $notice = ['kind' => 'error', 'title' => 'Security check failed',
                       'text'  => 'The state parameter did not match. Start the connection again.'];
        } else {
            $res = hh_ig_exchange_code((string) $_GET['code'], $appId, $appSecret, $redirectUri);
            if ($res['ok'] && hh_ig_token_save($res['token'])) {
                @unlink($cfg['cache_file']);   // force a fresh pull
                $who    = $res['token']['username'] !== '' ? '@' . $res['token']['username'] : 'your account';
                $notice = ['kind' => 'ok', 'title' => 'Connected',
                           'text'  => 'Instagram is now linked to ' . $who . '. The website will show real posts within the hour, or hit "Fetch now" below.'];
            } else {
                $notice = ['kind' => 'error', 'title' => 'Could not connect', 'text' => $res['error'] ?: 'Could not write the token file — check that storage/ is writable.'];
            }
        }
    }

    if (isset($_GET['error'])) {
        $notice = ['kind' => 'error', 'title' => 'Instagram declined the request',
                   'text'  => (string) ($_GET['error_description'] ?? $_GET['error'])];
    }

    // Manual refresh.
    if (($_POST['action'] ?? '') === 'refresh') {
        $token = hh_ig_token_load();
        if ($token === null) {
            $notice = ['kind' => 'error', 'title' => 'Nothing to refresh', 'text' => 'Connect the account first.'];
        } else {
            $res = hh_ig_refresh_token($token);
            if ($res['ok'] && hh_ig_token_save($res['token'])) {
                $notice = ['kind' => 'ok', 'title' => 'Token refreshed', 'text' => 'Valid for another 60 days.'];
            } else {
                $notice = ['kind' => 'error', 'title' => 'Refresh failed', 'text' => $res['error']];
            }
        }
    }

    // Force a fetch.
    if (($_POST['action'] ?? '') === 'fetch') {
        @unlink($cfg['cache_file']);
        @unlink($cfg['cache_file'] . '.fail');
        $feed = hh_instagram_feed($cfg);
        if ($feed['source'] === 'fallback') {
            $notice = ['kind' => 'error', 'title' => 'No posts yet',
                       'text'  => 'Instagram returned no media. Run the connection test below for the exact reason.'];
        } else {
            $reels = count(array_filter($feed['items'], static fn($i) => $i['type'] === 'REEL'));
            $notice = ['kind' => 'ok',
                       'title' => 'Fetched ' . count($feed['items']) . ' posts (' . $reels . ' reels)',
                       'text'  => 'Thumbnails mirrored locally. Reload the homepage to see them.'];
        }
    }

    // Can this server reach Instagram's public endpoint at all?
    if (($_POST['action'] ?? '') === 'probe') {
        $t0 = microtime(true);
        $ch = curl_init(HH_IG_PROFILE_API . rawurlencode(HH_IG_HANDLE));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36',
            CURLOPT_HTTPHEADER     => ['x-ig-app-id: ' . HH_IG_WEB_APP_ID, 'Accept: */*', 'Accept-Language: en-US,en;q=0.9'],
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $ms = (int) round((microtime(true) - $t0) * 1000);

        $user = is_string($body) ? (json_decode($body, true)['data']['user'] ?? null) : null;

        if ($code === 200 && is_array($user)) {
            $total = (int) ($user['edge_owner_to_timeline_media']['count'] ?? 0);
            $vids  = count(array_filter(
                (array) ($user['edge_owner_to_timeline_media']['edges'] ?? []),
                static fn($e) => !empty($e['node']['is_video'])
            ));
            $probe = ['ok' => true, 'text' => sprintf(
                'HTTP 200 in %dms — %s followers, %d posts, %d reels in the latest batch.',
                $ms, number_format((int) ($user['edge_followed_by']['count'] ?? 0)), $total, $vids
            )];
        } else {
            $msg = is_string($body) ? (json_decode($body, true)['message'] ?? '') : '';
            $probe = ['ok' => false, 'text' => sprintf(
                'HTTP %d in %dms%s — this server cannot read the public endpoint. %s',
                $code, $ms, $msg !== '' ? ' (“' . $msg . '”)' : '',
                'Connect via OAuth below, or ask your host about the outbound IP.'
            )];
        }
    }

    // Disconnect.
    if (($_POST['action'] ?? '') === 'disconnect') {
        @unlink(hh_ig_token_file());
        @unlink($cfg['cache_file']);
        $notice = ['kind' => 'info', 'title' => 'Disconnected', 'text' => 'The stored token has been deleted.'];
    }
}

// ---------------------------------------------------------------------------
// Current state
// ---------------------------------------------------------------------------
$token     = $authed ? hh_ig_token_load() : null;
$connected = $token !== null;
$daysLeft  = $connected ? (int) floor(($token['expires_at'] - time()) / 86400) : 0;
$expired   = $connected && $daysLeft <= 0;

$cached = [];
if ($authed && is_file($cfg['cache_file'])) {
    $raw    = json_decode((string) file_get_contents($cfg['cache_file']), true);
    $cached = is_array($raw) ? ($raw['data']['items'] ?? []) : [];
}
$liveCached = $cached !== [] && ($cached[0]['type'] ?? 'FALLBACK') !== 'FALLBACK';

if ($authed && $appId !== '' && $appSecret !== '') {
    $_SESSION['hh_ig_state'] = bin2hex(random_bytes(12));
    $authUrl = hh_ig_authorize_url($appId, $redirectUri, $_SESSION['hh_ig_state']);
} else {
    $authUrl = '';
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Connect Instagram — Heal Health Homeopathy</title>
<link rel="icon" href="assets/img/favicon.svg" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400..700&family=Inter:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="assets/css/style.css?v=2">
<style>
  body { background: var(--wash); }
  .setup { max-width: 780px; margin: 0 auto; padding: clamp(32px, 6vw, 64px) var(--gutter) 80px; }
  .setup h1 { font-size: clamp(28px, 4vw, 38px); margin-bottom: 10px; }
  .setup > p.lede { margin-bottom: 34px; }
  .panel { background: var(--surface); border: 1px solid var(--ink-100); border-radius: var(--r-lg); padding: 28px; margin-bottom: 20px; }
  .panel h2 { font-size: 20px; margin-bottom: 6px; }
  .panel h3 { font-size: 16px; font-family: var(--font-body); font-weight: 700; margin: 22px 0 8px; }
  .panel p, .panel li { font-size: 15px; color: var(--ink-500); }
  .status { display: flex; align-items: center; gap: 12px; font-size: 15px; font-weight: 600; margin-bottom: 14px; }
  .dot { width: 11px; height: 11px; border-radius: 50%; flex-shrink: 0; }
  .dot--on { background: #22a06b; box-shadow: 0 0 0 4px rgba(34,160,107,.16); }
  .dot--off { background: #c9a227; box-shadow: 0 0 0 4px rgba(201,162,39,.16); }
  .dot--bad { background: #d9534f; box-shadow: 0 0 0 4px rgba(217,83,79,.16); }
  .kv { display: grid; grid-template-columns: 170px 1fr; gap: 8px 16px; font-size: 14.5px; margin-top: 16px; }
  .kv dt { color: var(--ink-400); font-weight: 600; }
  .kv dd { margin: 0; color: var(--ink-900); word-break: break-all; }
  ol.steps-list { counter-reset: s; display: grid; gap: 16px; margin: 0; padding: 0; }
  ol.steps-list li { counter-increment: s; list-style: none; display: grid; grid-template-columns: 30px 1fr; gap: 14px; align-items: start; }
  ol.steps-list li::before {
    content: counter(s); width: 30px; height: 30px; border-radius: 50%;
    background: var(--green-50); color: var(--green-700);
    display: grid; place-items: center; font-size: 13px; font-weight: 700;
  }
  code.copy { display: block; background: var(--green-900); color: #cfe9df; padding: 12px 15px; border-radius: 9px; font-size: 13px; word-break: break-all; margin-top: 8px; font-family: ui-monospace, Menlo, monospace; }
  .btn-row { display: flex; gap: 12px; flex-wrap: wrap; margin-top: 22px; }
  .thumbs { display: grid; grid-template-columns: repeat(auto-fill, minmax(100px, 1fr)); gap: 10px; margin-top: 18px; }
  .thumbs figure { margin: 0; }
  .thumbs img { width: 100%; aspect-ratio: 1; object-fit: cover; border-radius: 10px; border: 1px solid var(--ink-100); }
  .thumbs figcaption { font-size: 11px; color: var(--ink-400); margin-top: 5px; font-weight: 600; }
  .inline-form { display: inline; }
</style>
</head>
<body>
<div class="setup">

  <h1>Instagram</h1>
  <p class="lede">
    The site pulls reels from <strong>@<?= e(HH_IG_HANDLE) ?></strong> automatically, with no login.
    Use this page to check that it is working, or to connect via OAuth if this server gets blocked.
  </p>

  <?php if ($notice !== null): ?>
    <div class="form-alert is-visible form-alert--<?= $notice['kind'] === 'ok' ? 'ok' : ($notice['kind'] === 'info' ? 'ok' : 'error') ?>">
      <span><?= hh_icon($notice['kind'] === 'error' ? 'shield' : 'check-c', 22) ?></span>
      <div><strong><?= e($notice['title']) ?></strong><span><?= e($notice['text']) ?></span></div>
    </div>
  <?php endif; ?>

  <?php if ($blocked): ?>
    <div class="panel">
      <h2>One step first</h2>
      <p>Open <code>includes/config.local.php</code> and set a password:</p>
      <code class="copy">'HH_IG_SETUP_KEY' =&gt; 'pick-something-long',</code>
      <p style="margin-top:14px;">If that file does not exist yet, copy <code>includes/config.local.sample.php</code> to <code>includes/config.local.php</code> first.</p>
    </div>

  <?php elseif (!$authed): ?>
    <div class="panel">
      <h2>Enter the setup password</h2>
      <p>This is the <code>HH_IG_SETUP_KEY</code> value from <code>includes/config.local.php</code>.</p>
      <form method="post" style="margin-top:18px;">
        <input type="hidden" name="action" value="login">
        <div class="field">
          <label for="key">Setup password</label>
          <input type="password" id="key" name="key" autocomplete="current-password" autofocus required>
        </div>
        <div class="btn-row"><button class="btn btn--primary" type="submit">Unlock</button></div>
      </form>
    </div>

  <?php else: ?>

    <!-- Connection test — the thing that actually matters -->
    <div class="panel">
      <h2>Connection test</h2>
      <p>Checks whether this server can read the public profile endpoint. That is the path the
         site uses by default, and it needs no credentials at all.</p>

      <?php if ($probe !== null): ?>
        <div class="status" style="margin-top:16px;">
          <span class="dot <?= $probe['ok'] ? 'dot--on' : 'dot--bad' ?>"></span>
          <?= $probe['ok'] ? 'Instagram is reachable' : 'Instagram is not reachable' ?>
        </div>
        <p style="margin-top:-6px;"><?= e($probe['text']) ?></p>
      <?php endif; ?>

      <div class="btn-row">
        <form class="inline-form" method="post"><input type="hidden" name="action" value="probe">
          <button class="btn btn--primary" type="submit">Test connection</button></form>
        <form class="inline-form" method="post"><input type="hidden" name="action" value="fetch">
          <button class="btn btn--ghost" type="submit">Fetch reels now</button></form>
      </div>

      <?php if ($liveCached): ?>
        <h3>Currently on the website</h3>
        <div class="thumbs">
          <?php foreach ($cached as $post): ?>
            <figure>
              <?php if (($post['image'] ?? '') !== ''): ?>
                <img src="<?= e($post['image']) ?>" alt="" loading="lazy">
              <?php endif; ?>
              <figcaption>
                <?= $post['type'] === 'REEL' ? 'Reel' : 'Post' ?>
                <?= ($post['views'] ?? 0) > 0 ? ' · ' . e(hh_ig_short_num((int) $post['views'])) . ' plays' : '' ?>
              </figcaption>
            </figure>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <!-- OAuth fallback -->
    <div class="panel">
      <h2>OAuth fallback <span style="font-weight:400;color:var(--ink-400);font-size:14px;">— optional</span></h2>
      <p>Only needed if the test above fails. Connecting the account through Instagram's official
         API gives a token the site will use instead.</p>

      <?php if ($connected && !$expired): ?>
        <div class="status"><span class="dot dot--on"></span> Connected<?= $token['username'] !== '' ? ' as @' . e($token['username']) : '' ?></div>
        <p>The token renews itself automatically while the site gets traffic.</p>
      <?php elseif ($expired): ?>
        <div class="status"><span class="dot dot--bad"></span> Token expired</div>
        <p>Reconnect the account below — an expired token cannot be refreshed.</p>
      <?php else: ?>
        <div class="status"><span class="dot dot--off"></span> Not connected — not needed while the test above passes</div>
      <?php endif; ?>

      <?php if ($connected): ?>
        <dl class="kv">
          <dt>Expires</dt>
          <dd><?= $expired ? 'Expired' : date('d M Y', $token['expires_at']) . ' (' . $daysLeft . ' days left)' ?></dd>
          <dt>Connected on</dt><dd><?= $token['obtained_at'] ? date('d M Y', $token['obtained_at']) : '—' ?></dd>
          <dt>Cached posts</dt><dd><?= $liveCached ? count($cached) . ' live posts' : 'none yet' ?></dd>
        </dl>
      <?php endif; ?>

      <div class="btn-row">
        <?php if ($authUrl !== ''): ?>
          <a class="btn btn--primary" href="<?= e($authUrl) ?>">
            <?= hh_icon('instagram', 17) ?> <?= $connected ? 'Reconnect' : 'Connect Instagram' ?>
          </a>
        <?php endif; ?>
        <?php if ($connected): ?>
          <form class="inline-form" method="post"><input type="hidden" name="action" value="refresh">
            <button class="btn btn--ghost" type="submit">Refresh token</button></form>
          <form class="inline-form" method="post" onsubmit="return confirm('Delete the stored Instagram token?');">
            <input type="hidden" name="action" value="disconnect">
            <button class="btn btn--ghost" type="submit">Disconnect</button></form>
        <?php endif; ?>
      </div>
    </div>

    <!-- Meta app setup -->
    <?php if ($appId === '' || $appSecret === ''): ?>
    <div class="panel">
      <h2>Create the Meta app</h2>
      <p>Instagram only releases posts to an app you own. This is free and takes about ten minutes, once.</p>
      <ol class="steps-list" style="margin-top:20px;">
        <li><div><strong>Make the account Professional.</strong> In the Instagram app: Settings &rarr; Account type and tools &rarr; Switch to professional account (Creator is fine). Personal accounts cannot use this API.</div></li>
        <li><div><strong>Create an app</strong> at <a href="https://developers.facebook.com/apps" target="_blank" rel="noopener noreferrer">developers.facebook.com/apps</a> &rarr; Create app &rarr; use case <em>Other</em> &rarr; type <em>Business</em>.</div></li>
        <li><div><strong>Add the product</strong> &ldquo;Instagram&rdquo; &rarr; <em>API setup with Instagram login</em>.</div></li>
        <li><div><strong>Add this exact redirect URI</strong> under &ldquo;Business login settings&rdquo;:<code class="copy"><?= e($redirectUri) ?></code></div></li>
        <li><div><strong>Copy the Instagram App ID and App Secret</strong> into <code>includes/config.local.php</code>:<code class="copy">'HH_IG_APP_ID'     =&gt; '...',<br>'HH_IG_APP_SECRET' =&gt; '...',</code></div></li>
        <li><div>Reload this page — a <strong>Connect Instagram</strong> button will appear.</div></li>
      </ol>
    </div>
    <?php else: ?>
    <div class="panel">
      <h2>App details</h2>
      <dl class="kv">
        <dt>Instagram App ID</dt><dd><?= e($appId) ?></dd>
        <dt>App secret</dt><dd>configured</dd>
        <dt>Redirect URI</dt><dd><?= e($redirectUri) ?></dd>
      </dl>
      <p style="margin-top:14px;">The redirect URI above must appear <em>exactly</em> in your Meta app under Instagram &rarr; Business login settings, or Instagram will refuse the connection.</p>
      <?php if ($scheme === 'http' && !str_starts_with($host, 'localhost') && !str_starts_with($host, '127.0.0.1')): ?>
        <p style="color:#a3302c;font-weight:600;margin-top:12px;">This page is being served over plain HTTP. Instagram requires an HTTPS redirect URI — enable SSL on the domain before connecting.</p>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="panel">
      <h2>Reels settings</h2>
      <p>Set these in <code>includes/config.local.php</code>:</p>
      <dl class="kv" style="margin-top:14px;">
        <dt>HH_IG_REELS_ONLY</dt><dd><?= $cfg['reels_only'] ? '1 — reels only' : '0 — reels and photo posts' ?></dd>
        <dt>HH_IG_REELS_FIRST</dt><dd><?= $cfg['reels_first'] ? '1 — reels shown first' : '0 — newest first regardless' ?></dd>
        <dt>HH_IG_LIMIT</dt><dd><?= (int) $cfg['limit'] ?> tiles</dd>
        <dt>Cache</dt><dd>refreshed every <?= (int) round($cfg['cache_ttl'] / 60) ?> minutes</dd>
      </dl>
    </div>

    <p style="text-align:center;font-size:14px;">
      <a href="index.php">&larr; Back to the website</a> &nbsp;·&nbsp;
      <a href="?logout=1">Lock this page</a>
    </p>
  <?php endif; ?>

</div>
</body>
</html>
