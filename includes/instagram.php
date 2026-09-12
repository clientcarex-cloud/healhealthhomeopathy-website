<?php
/**
 * Instagram feed — real reels from @dr.atiya.healhealthhomeopathy.
 *
 * PRIMARY path needs no token and no OAuth: Instagram's own web endpoint
 * `web_profile_info` returns a public profile's recent media when called with
 * the public web app id. This is what the Stallion site uses in production.
 * It is an unofficial endpoint, so it can rate-limit a given server IP — when
 * that happens we serve the last good cache and retry shortly, and the
 * optional OAuth path below can take over permanently.
 *
 * FALLBACK path (optional): if the account has been connected through
 * /setup-instagram.php, the official Graph API is used instead.
 *
 * Thumbnails are always mirrored into assets/img/ig/ because fbcdn URLs are
 * signed, short-lived, and refuse browser hotlinks.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';

/** Public web app id used by instagram.com itself. */
const HH_IG_WEB_APP_ID  = '936619743392459';
const HH_IG_PROFILE_API = 'https://www.instagram.com/api/v1/users/web_profile_info/?username=';

const HH_IG_GRAPH        = 'https://graph.instagram.com';
const HH_IG_OAUTH_AUTH   = 'https://www.instagram.com/oauth/authorize';
const HH_IG_OAUTH_TOKEN  = 'https://api.instagram.com/oauth/access_token';
const HH_IG_SCOPES       = 'instagram_business_basic';

/** Refresh the long-lived token when fewer than this many days remain. */
const HH_IG_REFRESH_WINDOW_DAYS = 10;

/**
 * Curated tiles — only ever shown if the account has never been connected,
 * or the API is down and there is no cache at all.
 */
const HH_IG_FALLBACK = [
    ['topic' => 'Case notes', 'caption' => 'How constitutional homeopathy approaches ADHD in children — a walk-through of one case.'],
    ['topic' => 'Skin',       'caption' => 'Psoriasis: why the flare returns, and what deep-acting treatment changes.'],
    ['topic' => 'Fertility',  'caption' => 'PCOS and cycle regulation — the three things I check before writing a prescription.'],
    ['topic' => 'Thyroid',    'caption' => 'Reading your thyroid report: TSH alone is not the whole story.'],
    ['topic' => 'Gut health', 'caption' => 'Acidity every evening? Here is what your gut is actually telling you.'],
    ['topic' => 'Clinic',     'caption' => 'Consultations now open at MPM Mall, Abids — and online for patients abroad.'],
];

// ---------------------------------------------------------------------------
// Token storage
// ---------------------------------------------------------------------------

function hh_ig_token_file(): string
{
    return dirname(__DIR__) . '/storage/instagram_token.json';
}

/**
 * @return array{access_token:string,expires_at:int,obtained_at:int,user_id:string,username:string}|null
 */
function hh_ig_token_load(): ?array
{
    $file = hh_ig_token_file();
    if (!is_file($file)) {
        return null;
    }

    $data = json_decode((string) file_get_contents($file), true);
    if (!is_array($data) || empty($data['access_token'])) {
        return null;
    }

    return $data + ['expires_at' => 0, 'obtained_at' => 0, 'user_id' => '', 'username' => ''];
}

function hh_ig_token_save(array $token): bool
{
    $file = hh_ig_token_file();
    $dir  = dirname($file);
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }

    $ok = @file_put_contents($file, json_encode($token, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
    @chmod($file, 0600);  // contains a credential

    return $ok !== false;
}

/**
 * The active token: the stored one (preferred, because it self-refreshes),
 * otherwise a token pasted straight into config.local.php.
 */
function hh_ig_access_token(array $cfg): string
{
    $stored = hh_ig_token_load();
    if ($stored !== null && $stored['access_token'] !== '') {
        return $stored['access_token'];
    }

    return $cfg['access_token'];
}

// ---------------------------------------------------------------------------
// HTTP
// ---------------------------------------------------------------------------

/**
 * GET a URL and JSON-decode it.
 *
 * @return array{ok:bool, status:int, data:array, error:string}
 */
function hh_ig_get_json(string $url, int $timeout = 10): array
{
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'status' => 0, 'data' => [], 'error' => 'cURL is not available on this server.'];
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT      => 'HealHealthHomeopathy/1.0',
    ]);
    $body   = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $curlEr = curl_error($ch);
    curl_close($ch);

    if (!is_string($body)) {
        return ['ok' => false, 'status' => $status, 'data' => [], 'error' => $curlEr ?: 'No response.'];
    }

    $data = json_decode($body, true);
    if (!is_array($data)) {
        return ['ok' => false, 'status' => $status, 'data' => [], 'error' => 'Malformed response from Instagram.'];
    }

    if ($status !== 200 || isset($data['error'])) {
        $msg = $data['error']['message'] ?? ('HTTP ' . $status);
        return ['ok' => false, 'status' => $status, 'data' => $data, 'error' => (string) $msg];
    }

    return ['ok' => true, 'status' => $status, 'data' => $data, 'error' => ''];
}

// ---------------------------------------------------------------------------
// OAuth (used by setup-instagram.php)
// ---------------------------------------------------------------------------

function hh_ig_authorize_url(string $appId, string $redirectUri, string $state): string
{
    return HH_IG_OAUTH_AUTH . '?' . http_build_query([
        'client_id'     => $appId,
        'redirect_uri'  => $redirectUri,
        'response_type' => 'code',
        'scope'         => HH_IG_SCOPES,
        'state'         => $state,
    ]);
}

/**
 * Authorization code -> short-lived token -> long-lived token.
 *
 * @return array{ok:bool, token:array, error:string}
 */
function hh_ig_exchange_code(string $code, string $appId, string $appSecret, string $redirectUri): array
{
    // Step 1: code -> short-lived token (POST).
    $ch = curl_init(HH_IG_OAUTH_TOKEN);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_POSTFIELDS     => http_build_query([
            'client_id'     => $appId,
            'client_secret' => $appSecret,
            'grant_type'    => 'authorization_code',
            'redirect_uri'  => $redirectUri,
            'code'          => $code,
        ]),
    ]);
    $body   = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    $short = is_string($body) ? json_decode($body, true) : null;
    if ($status !== 200 || !is_array($short) || empty($short['access_token'])) {
        $msg = $short['error_message'] ?? $short['error']['message'] ?? ('HTTP ' . $status);
        return ['ok' => false, 'token' => [], 'error' => 'Code exchange failed: ' . $msg];
    }

    // Step 2: short-lived -> long-lived (60 days).
    $long = hh_ig_get_json(HH_IG_GRAPH . '/access_token?' . http_build_query([
        'grant_type'    => 'ig_exchange_token',
        'client_secret' => $appSecret,
        'access_token'  => $short['access_token'],
    ]), 15);

    if (!$long['ok'] || empty($long['data']['access_token'])) {
        return ['ok' => false, 'token' => [], 'error' => 'Long-lived token exchange failed: ' . $long['error']];
    }

    $token = [
        'access_token' => (string) $long['data']['access_token'],
        'expires_at'   => time() + (int) ($long['data']['expires_in'] ?? 5183944),
        'obtained_at'  => time(),
        'user_id'      => (string) ($short['user_id'] ?? ''),
        'username'     => '',
    ];

    // Best-effort: record the username so the setup page can confirm the account.
    $me = hh_ig_get_json(HH_IG_GRAPH . '/me?' . http_build_query([
        'fields'       => 'user_id,username',
        'access_token' => $token['access_token'],
    ]));
    if ($me['ok']) {
        $token['username'] = (string) ($me['data']['username'] ?? '');
        $token['user_id']  = (string) ($me['data']['user_id'] ?? $token['user_id']);
    }

    return ['ok' => true, 'token' => $token, 'error' => ''];
}

/**
 * Extend a long-lived token by another 60 days.
 *
 * @return array{ok:bool, token:array, error:string}
 */
function hh_ig_refresh_token(array $token): array
{
    $res = hh_ig_get_json(HH_IG_GRAPH . '/refresh_access_token?' . http_build_query([
        'grant_type'   => 'ig_refresh_token',
        'access_token' => $token['access_token'],
    ]), 15);

    if (!$res['ok'] || empty($res['data']['access_token'])) {
        return ['ok' => false, 'token' => $token, 'error' => $res['error']];
    }

    $token['access_token'] = (string) $res['data']['access_token'];
    $token['expires_at']   = time() + (int) ($res['data']['expires_in'] ?? 5183944);
    $token['obtained_at']  = time();

    return ['ok' => true, 'token' => $token, 'error' => ''];
}

/**
 * Refresh the stored token if it is close to expiring. Called on every fetch,
 * so an active site keeps its connection alive indefinitely.
 */
function hh_ig_maybe_refresh(): void
{
    $token = hh_ig_token_load();
    if ($token === null || $token['expires_at'] <= 0) {
        return;
    }

    $daysLeft = ($token['expires_at'] - time()) / 86400;

    // Already dead — refreshing will not help; the account must be reconnected.
    if ($daysLeft <= 0 || $daysLeft > HH_IG_REFRESH_WINDOW_DAYS) {
        return;
    }

    $res = hh_ig_refresh_token($token);
    if ($res['ok']) {
        hh_ig_token_save($res['token']);
    } else {
        error_log('[HealHealth] Instagram token refresh failed: ' . $res['error']);
    }
}

// ---------------------------------------------------------------------------
// Feed
// ---------------------------------------------------------------------------

/**
 * The feed shown on the site.
 *
 * @return array{handle:string,followers:int,posts:int,items:array,source:string}
 */
function hh_instagram_feed(array $cfg): array
{
    $cacheFile = $cfg['cache_file'];
    $handle    = HH_IG_HANDLE;
    $ttl       = max(300, (int) $cfg['cache_ttl']);

    // 1. Fresh cache wins.
    $cache = is_file($cacheFile)
        ? json_decode((string) file_get_contents($cacheFile), true)
        : null;

    if (is_array($cache)
        && ($cache['handle'] ?? '') === $handle
        && (int) ($cache['v'] ?? 0) === 3
        && (time() - (int) ($cache['ts'] ?? 0)) < $ttl) {
        return $cache['data'] + ['source' => 'cache'];
    }

    // 2. Public endpoint — no credentials needed.
    $data = hh_ig_fetch_public($handle, $cfg);

    // 3. Official API, if the account was connected via /setup-instagram.php.
    if ($data === null) {
        $token = hh_ig_access_token($cfg);
        if ($token !== '') {
            hh_ig_maybe_refresh();
            $data = hh_ig_fetch_graph(hh_ig_access_token($cfg), $cfg);
        }
    }

    if ($data !== null) {
        hh_ig_write_cache($cacheFile, $handle, $data);
        return $data + ['source' => 'live'];
    }

    // 4. Instagram unreachable — serve the stale copy, but retry in 15 minutes
    //    instead of hammering the endpoint on every page view.
    if (is_array($cache) && ($cache['handle'] ?? '') === $handle && (int) ($cache['v'] ?? 0) === 3) {
        $cache['ts'] = time() - $ttl + 900;
        @file_put_contents($cacheFile, json_encode($cache, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), LOCK_EX);

        return $cache['data'] + ['source' => 'stale'];
    }

    // 5. Never fetched successfully — curated tiles.
    return [
        'handle'    => $handle,
        'followers' => 0,
        'posts'     => 0,
        'items'     => hh_instagram_fallback(),
        'source'    => 'fallback',
    ];
}

function hh_ig_write_cache(string $file, string $handle, array $data): void
{
    $dir = dirname($file);
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }

    @file_put_contents(
        $file,
        json_encode(['handle' => $handle, 'v' => 3, 'ts' => time(), 'data' => $data], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        LOCK_EX
    );
}

/**
 * Public profile endpoint — the no-setup path.
 *
 * @return array{handle:string,followers:int,posts:int,items:array}|null
 */
function hh_ig_fetch_public(string $handle, array $cfg): ?array
{
    if (!function_exists('curl_init')) {
        return null;
    }

    $ch = curl_init(HH_IG_PROFILE_API . rawurlencode($handle));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36',
        CURLOPT_HTTPHEADER     => [
            'x-ig-app-id: ' . HH_IG_WEB_APP_ID,
            'Accept: */*',
            'Accept-Language: en-US,en;q=0.9',
        ],
    ]);
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code !== 200 || !is_string($body) || $body === '') {
        error_log('[HealHealth] Instagram public endpoint returned HTTP ' . $code);
        return null;
    }

    $json = json_decode($body, true);
    $user = $json['data']['user'] ?? null;
    if (!is_array($user)) {
        return null;
    }

    $media = [];
    foreach ((array) ($user['edge_owner_to_timeline_media']['edges'] ?? []) as $edge) {
        $n = $edge['node'] ?? [];
        if (empty($n['shortcode'])) {
            continue;
        }

        $isVideo = !empty($n['is_video']);

        $caption = '';
        foreach ((array) ($n['edge_media_to_caption']['edges'] ?? []) as $c) {
            $caption = (string) ($c['node']['text'] ?? '');
            break;
        }

        $media[] = [
            'id'        => (string) $n['shortcode'],
            'type'      => $isVideo ? 'REEL' : (!empty($n['edge_sidecar_to_children']) ? 'CAROUSEL_ALBUM' : 'IMAGE'),
            'permalink' => 'https://www.instagram.com/' . ($isVideo ? 'reel' : 'p') . '/' . $n['shortcode'] . '/',
            'remote'    => (string) ($n['thumbnail_src'] ?? $n['display_url'] ?? ''),
            'caption'   => hh_ig_clean_caption($caption),
            'views'     => (int) ($n['video_view_count'] ?? $n['video_play_count'] ?? 0),
            'likes'     => (int) ($n['edge_liked_by']['count'] ?? $n['edge_media_preview_like']['count'] ?? 0),
            'comments'  => (int) ($n['edge_media_to_comment']['count'] ?? 0),
            'taken'     => (int) ($n['taken_at_timestamp'] ?? 0),
        ];
    }

    if ($media === []) {
        return null;
    }

    return [
        'handle'    => $handle,
        'followers' => (int) ($user['edge_followed_by']['count'] ?? 0),
        'posts'     => (int) ($user['edge_owner_to_timeline_media']['count'] ?? 0),
        'items'     => hh_ig_shape($media, $cfg),
    ];
}

/**
 * Official Graph API — used only when the public endpoint is blocked
 * and the account has been connected through /setup-instagram.php.
 */
function hh_ig_fetch_graph(string $token, array $cfg): ?array
{
    $fields = 'id,caption,media_type,media_product_type,media_url,permalink,thumbnail_url,timestamp,like_count,comments_count';

    $res = hh_ig_get_json(HH_IG_GRAPH . '/me/media?' . http_build_query([
        'fields'       => $fields,
        'limit'        => max(25, $cfg['limit'] * 4),
        'access_token' => $token,
    ]));

    if (!$res['ok']) {
        error_log('[HealHealth] Instagram Graph fetch failed: ' . $res['error']);
        return null;
    }

    $media = [];
    foreach ((array) ($res['data']['data'] ?? []) as $item) {
        $isVideo = ($item['media_type'] ?? '') === 'VIDEO';
        $isReel  = ($item['media_product_type'] ?? '') === 'REELS' || $isVideo;

        // Derive the shortcode from the permalink so filenames match the public path.
        $slug = '';
        if (preg_match('#/(?:reel|p|tv)/([A-Za-z0-9_-]+)#', (string) ($item['permalink'] ?? ''), $m)) {
            $slug = $m[1];
        }

        $media[] = [
            'id'        => $slug !== '' ? $slug : (string) ($item['id'] ?? ''),
            'type'      => $isReel ? 'REEL' : (string) ($item['media_type'] ?? 'IMAGE'),
            'permalink' => (string) ($item['permalink'] ?? HH_INSTAGRAM),
            'remote'    => $isVideo ? (string) ($item['thumbnail_url'] ?? '') : (string) ($item['media_url'] ?? ''),
            'caption'   => hh_ig_clean_caption((string) ($item['caption'] ?? '')),
            'views'     => 0,   // needs the Insights permission
            'likes'     => (int) ($item['like_count'] ?? 0),
            'comments'  => (int) ($item['comments_count'] ?? 0),
            'taken'     => strtotime((string) ($item['timestamp'] ?? '')) ?: 0,
        ];
    }

    if ($media === []) {
        return null;
    }

    return [
        'handle'    => HH_IG_HANDLE,
        'followers' => 0,
        'posts'     => count($media),
        'items'     => hh_ig_shape($media, $cfg),
    ];
}

/**
 * Filter to reels, sort newest first, trim to the limit, mirror thumbnails.
 */
function hh_ig_shape(array $media, array $cfg): array
{
    $isReel = static fn(array $m): bool => $m['type'] === 'REEL';

    if (!empty($cfg['reels_only'])) {
        $media = array_values(array_filter($media, $isReel));
    } elseif (!empty($cfg['reels_first'])) {
        $reels = array_values(array_filter($media, $isReel));
        $rest  = array_values(array_filter($media, static fn($m) => !$isReel($m)));
        usort($reels, static fn($a, $b) => $b['taken'] <=> $a['taken']);
        usort($rest,  static fn($a, $b) => $b['taken'] <=> $a['taken']);
        $media = array_merge($reels, $rest);
    } else {
        usort($media, static fn($a, $b) => $b['taken'] <=> $a['taken']);
    }

    if (!empty($cfg['reels_only'])) {
        usort($media, static fn($a, $b) => $b['taken'] <=> $a['taken']);
    }

    $media = array_slice($media, 0, max(1, (int) $cfg['limit']));

    foreach ($media as &$m) {
        $local = $m['remote'] !== '' ? hh_ig_cache_image($m['remote'], $m['id']) : '';
        $m['image'] = $local !== '' ? $local : '';
        unset($m['remote']);
    }
    unset($m);

    // Drop thumbnails for posts that have left the feed.
    hh_ig_prune_images($media);

    return $media;
}

/**
 * Hashtag and @mention soup makes a poor caption on a clinical site.
 */
function hh_ig_clean_caption(string $caption): string
{
    $clean = preg_replace(['/[#@][\w.]+/u', '/\s+/u'], ['', ' '], trim($caption)) ?? '';

    return mb_substr(trim($clean), 0, 150);
}

/**
 * 2100 -> "2.1K". Instagram-style short counts.
 */
function hh_ig_short_num(int $n): string
{
    if ($n >= 1000000) {
        return rtrim(rtrim(number_format($n / 1000000, 1), '0'), '.') . 'M';
    }
    if ($n >= 1000) {
        return rtrim(rtrim(number_format($n / 1000, 1), '0'), '.') . 'K';
    }

    return (string) $n;
}

/**
 * Mirror a thumbnail into assets/img/ig/ and return its web path.
 */
function hh_ig_cache_image(string $url, string $mediaId): string
{
    $slug = preg_replace('/[^A-Za-z0-9_-]/', '', $mediaId) ?? '';
    if ($slug === '') {
        return '';
    }

    $dir = dirname(__DIR__) . '/assets/img/ig';
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
        return '';
    }

    $path = $dir . '/' . $slug . '.jpg';
    $web  = 'assets/img/ig/' . $slug . '.jpg';

    if (is_file($path) && filesize($path) > 1000) {
        return $web . '?v=' . filemtime($path);
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36',
    ]);
    $bytes = curl_exec($ch);
    $code  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Verify it really is an image before writing it.
    if ($code !== 200 || !is_string($bytes) || strlen($bytes) < 1000) {
        return '';
    }
    if (function_exists('imagecreatefromstring') && @imagecreatefromstring($bytes) === false) {
        return '';
    }

    if (@file_put_contents($path, $bytes, LOCK_EX) === false) {
        return '';
    }

    return $web . '?v=' . filemtime($path);
}

/**
 * Delete mirrored thumbnails that are no longer in the feed.
 */
function hh_ig_prune_images(array $items): void
{
    $dir = dirname(__DIR__) . '/assets/img/ig';
    if (!is_dir($dir)) {
        return;
    }

    $keep = [];
    foreach ($items as $item) {
        $slug = preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($item['id'] ?? '')) ?? '';
        if ($slug !== '') {
            $keep[$slug . '.jpg'] = true;
        }
    }

    if ($keep === []) {
        return;
    }

    foreach (glob($dir . '/*.jpg') ?: [] as $file) {
        if (!isset($keep[basename($file)])) {
            @unlink($file);
        }
    }
}

function hh_instagram_fallback(): array
{
    $items = [];
    foreach (HH_IG_FALLBACK as $i => $tile) {
        $items[] = [
            'id'        => 'fallback-' . $i,
            'type'      => 'FALLBACK',
            'permalink' => HH_INSTAGRAM,
            'image'     => '',
            'caption'   => $tile['caption'],
            'topic'     => $tile['topic'],
            'views'     => 0,
            'likes'     => 0,
            'comments'  => 0,
            'taken'     => 0,
        ];
    }

    return $items;
}

/**
 * "1w ago", "3 days ago", ...
 */
function hh_instagram_when(int $taken): string
{
    if ($taken <= 0) {
        return '';
    }

    $secs = time() - $taken;

    return match (true) {
        $secs < 3600    => 'Just now',
        $secs < 86400   => (int) floor($secs / 3600) . 'h ago',
        $secs < 172800  => 'Yesterday',
        $secs < 604800  => (int) floor($secs / 86400) . 'd ago',
        $secs < 2592000 => (int) floor($secs / 604800) . 'w ago',
        default         => date('d M Y', $taken),
    };
}
