<?php
/**
 * Instagram feed.
 *
 * With HH_IG_TOKEN set, posts are pulled live from the Instagram Graph API
 * and cached to storage/cache/instagram.json. Without a token (or if the API
 * is unreachable) the site renders curated fallback tiles that link to the
 * profile, so the section never appears broken.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';

/**
 * Curated tiles shown when no live feed is available.
 * Edit the captions here any time; they link to the profile.
 */
const HH_IG_FALLBACK = [
    ['topic' => 'Case notes',   'caption' => 'How constitutional homeopathy approaches ADHD in children — a walk-through of one case.'],
    ['topic' => 'Skin',         'caption' => 'Psoriasis: why the flare returns, and what deep-acting treatment changes.'],
    ['topic' => 'Fertility',    'caption' => 'PCOS and cycle regulation — the three things I check before writing a prescription.'],
    ['topic' => 'Thyroid',      'caption' => 'Reading your thyroid report: TSH alone is not the whole story.'],
    ['topic' => 'Gut health',   'caption' => 'Acidity every evening? Here is what your gut is actually telling you.'],
    ['topic' => 'Clinic',       'caption' => 'Consultations now open at MPM Mall, Abids — and online for patients abroad.'],
];

/**
 * Fetch the latest posts. Always returns a list of tiles.
 *
 * @return array<int, array{type:string, permalink:string, image:string, caption:string, topic:string, timestamp:string}>
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

    // 2. Try the live API.
    if ($cfg['access_token'] !== '') {
        $live = hh_instagram_fetch_live($cfg);
        if ($live !== []) {
            $dir = dirname($cacheFile);
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            @file_put_contents($cacheFile, json_encode($live, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), LOCK_EX);

            return $live;
        }

        // API failed — serve a stale cache rather than nothing.
        if (is_file($cacheFile)) {
            $stale = json_decode((string) file_get_contents($cacheFile), true);
            if (is_array($stale) && $stale !== []) {
                return $stale;
            }
        }
    }

    // 3. Curated fallback.
    return hh_instagram_fallback();
}

/**
 * Call the Instagram Graph API.
 */
function hh_instagram_fetch_live(array $cfg): array
{
    if (!function_exists('curl_init')) {
        return [];
    }

    $url = 'https://graph.instagram.com/me/media?' . http_build_query([
        'fields'       => 'id,caption,media_type,media_url,permalink,thumbnail_url,timestamp',
        'limit'        => $cfg['limit'],
        'access_token' => $cfg['access_token'],
    ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT      => 'HealHealthHomeopathy/1.0',
    ]);
    $response = curl_exec($ch);
    $status   = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($status !== 200 || !is_string($response)) {
        error_log('[HealHealth] Instagram API returned HTTP ' . $status);
        return [];
    }

    $data = json_decode($response, true);
    if (!isset($data['data']) || !is_array($data['data'])) {
        return [];
    }

    $posts = [];
    foreach ($data['data'] as $item) {
        $image = $item['media_type'] === 'VIDEO'
            ? ($item['thumbnail_url'] ?? '')
            : ($item['media_url'] ?? '');

        $posts[] = [
            'type'      => (string) ($item['media_type'] ?? 'IMAGE'),
            'permalink' => (string) ($item['permalink'] ?? HH_INSTAGRAM),
            'image'     => (string) $image,
            'caption'   => mb_substr((string) ($item['caption'] ?? ''), 0, 180),
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
 * Build the curated fallback tiles.
 */
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
