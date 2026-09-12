<?php
/**
 * Instagram feed — real posts/reels via the Instagram API with Instagram Login.
 *
 * Instagram exposes NO public data without authentication (the profile page is
 * a JS shell and i.instagram.com returns require_login), so the account must be
 * connected once through OAuth. Run /setup-instagram.php to do that.
 *
 * Once connected this module:
 *   - fetches the latest media (reels first, or reels only)
 *   - mirrors each thumbnail locally, because Instagram CDN URLs are signed and
 *     expire after a few days — hotlinking them means broken images later
 *   - refreshes the 60-day long-lived token automatically before it expires
 *   - serves a stale cache, then curated tiles, if the API is ever unreachable
 *
 * @see https://developers.facebook.com/docs/instagram-platform/instagram-api-with-instagram-login
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';

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
// Media
// ---------------------------------------------------------------------------

/**
 * Fetch the latest posts. Always returns a list of tiles.
 */
function hh_instagram_posts(array $cfg): array
{
    $cacheFile = $cfg['cache_file'];

    // 1. Fresh cache wins.
    if (is_file($cacheFile) && (time() - (int) filemtime($cacheFile)) < $cfg['cache_ttl']) {
        $cached = json_decode((string) file_get_contents($cacheFile), true);
        if (is_array($cached) && $cached !== []) {
            return $cached;
        }
    }

    $token = hh_ig_access_token($cfg);

    if ($token !== '') {
        hh_ig_maybe_refresh();
        $token = hh_ig_access_token($cfg);   // may have just been rotated

        $live = hh_instagram_fetch_live($token, $cfg);
        if ($live !== []) {
            $dir = dirname($cacheFile);
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            @file_put_contents($cacheFile, json_encode($live, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), LOCK_EX);

            return $live;
        }

        // API failed — a stale cache beats nothing.
        if (is_file($cacheFile)) {
            $stale = json_decode((string) file_get_contents($cacheFile), true);
            if (is_array($stale) && $stale !== []) {
                return $stale;
            }
        }
    }

    return hh_instagram_fallback();
}

/**
 * Call the Graph API and normalise the response.
 */
function hh_instagram_fetch_live(string $token, array $cfg): array
{
    $fields = 'id,caption,media_type,media_product_type,media_url,permalink,thumbnail_url,timestamp';

    // Over-fetch so that filtering to reels still fills the grid.
    $res = hh_ig_get_json(HH_IG_GRAPH . '/me/media?' . http_build_query([
        'fields'       => $fields,
        'limit'        => max(25, $cfg['limit'] * 4),
        'access_token' => $token,
    ]));

    // media_product_type is unavailable on some account types — retry without it.
    if (!$res['ok'] && str_contains(strtolower($res['error']), 'media_product_type')) {
        $res = hh_ig_get_json(HH_IG_GRAPH . '/me/media?' . http_build_query([
            'fields'       => 'id,caption,media_type,media_url,permalink,thumbnail_url,timestamp',
            'limit'        => max(25, $cfg['limit'] * 4),
            'access_token' => $token,
        ]));
    }

    if (!$res['ok']) {
        error_log('[HealHealth] Instagram media fetch failed: ' . $res['error']);
        return [];
    }

    $items = $res['data']['data'] ?? [];
    if (!is_array($items) || $items === []) {
        return [];
    }

    $isReel = static function (array $item): bool {
        if (($item['media_product_type'] ?? '') === 'REELS') {
            return true;
        }
        // Fallback for accounts that do not return media_product_type.
        return ($item['media_type'] ?? '') === 'VIDEO';
    };

    // Reels only, or reels first then everything else.
    if (!empty($cfg['reels_only'])) {
        $items = array_values(array_filter($items, $isReel));
    } elseif (!empty($cfg['reels_first'])) {
        $reels = array_values(array_filter($items, $isReel));
        $rest  = array_values(array_filter($items, static fn($i) => !$isReel($i)));
        $items = array_merge($reels, $rest);
    }

    $posts = [];
    foreach ($items as $item) {
        $remote = ($item['media_type'] ?? '') === 'VIDEO'
            ? (string) ($item['thumbnail_url'] ?? '')
            : (string) ($item['media_url'] ?? '');

        // Instagram CDN URLs are signed and expire — keep our own copy.
        $local = $remote !== ''
            ? hh_ig_cache_image($remote, (string) ($item['id'] ?? ''))
            : '';

        $posts[] = [
            'type'      => $isReel($item) ? 'REEL' : (string) ($item['media_type'] ?? 'IMAGE'),
            'permalink' => (string) ($item['permalink'] ?? HH_INSTAGRAM),
            'image'     => $local !== '' ? $local : $remote,
            'caption'   => mb_substr(trim((string) ($item['caption'] ?? '')), 0, 180),
            'topic'     => '',
            'timestamp' => (string) ($item['timestamp'] ?? ''),
        ];

        if (count($posts) >= $cfg['limit']) {
            break;
        }
    }

    return $posts;
}

/**
 * Mirror a thumbnail into assets/img/ig/ and return its web path.
 * Returns '' if the download fails, so the caller can hotlink as a last resort.
 */
function hh_ig_cache_image(string $url, string $mediaId): string
{
    if ($mediaId === '') {
        return '';
    }

    $dir = dirname(__DIR__) . '/assets/img/ig';
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
        return '';
    }

    $name = preg_replace('/[^A-Za-z0-9_-]/', '', $mediaId) . '.jpg';
    $path = $dir . '/' . $name;
    $web  = 'assets/img/ig/' . $name;

    // Already mirrored and still recent enough.
    if (is_file($path) && filesize($path) > 0 && (time() - (int) filemtime($path)) < 30 * 86400) {
        return $web;
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT      => 'HealHealthHomeopathy/1.0',
    ]);
    $bytes  = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $ctype  = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);

    if ($status !== 200 || !is_string($bytes) || $bytes === '' || !str_starts_with($ctype, 'image/')) {
        return '';
    }

    return @file_put_contents($path, $bytes, LOCK_EX) !== false ? $web : '';
}

/**
 * Delete mirrored thumbnails that are no longer in the feed.
 */
function hh_ig_prune_images(array $posts): void
{
    $dir = dirname(__DIR__) . '/assets/img/ig';
    if (!is_dir($dir)) {
        return;
    }

    $keep = [];
    foreach ($posts as $post) {
        if (str_starts_with((string) $post['image'], 'assets/img/ig/')) {
            $keep[basename($post['image'])] = true;
        }
    }

    foreach (glob($dir . '/*.jpg') ?: [] as $file) {
        if (!isset($keep[basename($file)]) && (time() - (int) filemtime($file)) > 7 * 86400) {
            @unlink($file);
        }
    }
}

function hh_instagram_fallback(): array
{
    $posts = [];
    foreach (HH_IG_FALLBACK as $tile) {
        $posts[] = [
            'type'      => 'FALLBACK',
            'permalink' => HH_INSTAGRAM,
            'image'     => '',
            'caption'   => $tile['caption'],
            'topic'     => $tile['topic'],
            'timestamp' => '',
        ];
    }

    return $posts;
}

/**
 * Humanise an ISO timestamp for display.
 */
function hh_instagram_when(string $timestamp): string
{
    if ($timestamp === '') {
        return '';
    }

    try {
        $then = new DateTimeImmutable($timestamp);
    } catch (Exception) {
        return '';
    }

    $days = (int) $then->diff(new DateTimeImmutable('now'))->days;

    return match (true) {
        $days <= 0  => 'Today',
        $days === 1 => 'Yesterday',
        $days < 7   => $days . ' days ago',
        $days < 30  => (int) round($days / 7) . ' weeks ago',
        default     => $then->format('d M Y'),
    };
}
