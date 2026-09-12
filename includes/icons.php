<?php
/**
 * Inline SVG icon set. Keeping icons inline avoids an icon-font request
 * and lets them inherit currentColor.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';  // for e()

function hh_icon(string $name, int $size = 20, string $class = ''): string
{
    $paths = [
        'phone'     => '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.9.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/>',
        'pin'       => '<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>',
        'clock'     => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
        'mail'      => '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 6L2 7"/>',
        'check'     => '<polyline points="20 6 9 17 4 12"/>',
        'check-c'   => '<circle cx="12" cy="12" r="10"/><polyline points="16 9.5 10.8 15 8 12.3"/>',
        'arrow'     => '<line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/>',
        'calendar'  => '<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>',
        'instagram' => '<rect x="2" y="2" width="20" height="20" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1.2" fill="currentColor" stroke="none"/>',
        'whatsapp'  => '<path d="M20.5 3.5A10.4 10.4 0 0 0 3.6 16.1L2.5 21.5l5.5-1.1a10.4 10.4 0 0 0 12.5-16.9z"/><path d="M8.4 8.2c.2-.5.4-.5.7-.5h.5c.2 0 .4 0 .6.5l.8 1.9c.1.3 0 .5-.1.7l-.4.5c-.1.2-.3.4-.1.7a7.3 7.3 0 0 0 3.4 3c.4.2.6 0 .7-.1l.6-.7c.2-.2.4-.2.6-.1l1.9.9c.3.2.4.3.4.5s0 .9-.3 1.3c-.3.4-1 .8-1.6.8-1.6.1-3.6-.9-5.2-2.3a11.6 11.6 0 0 1-2.7-4c-.3-.9-.2-2 .3-2.6z" fill="currentColor" stroke="none"/>',
        'play'      => '<polygon points="6 3 20 12 6 21 6 3" fill="currentColor" stroke="none"/>',
        'heart'     => '<path d="M19.5 12.6 12 20l-7.5-7.4a4.9 4.9 0 0 1 7-6.9l.5.5.5-.5a4.9 4.9 0 0 1 7 6.9z" fill="currentColor" stroke="none"/>',
        'layers'    => '<polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/>',
        'shield'    => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><polyline points="9 12 11 14 15 10"/>',
        'globe'     => '<circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>',
        'award'     => '<circle cx="12" cy="8" r="6"/><polyline points="8.2 13.4 7 22 12 19 17 22 15.8 13.4"/>',
        'users'     => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'stethoscope' => '<path d="M4.8 2.3v5a4.2 4.2 0 0 0 8.4 0v-5"/><path d="M4.8 2.3H3.2M13.2 2.3h1.6"/><path d="M9 11.5v3a5.5 5.5 0 0 0 11 0v-1.6"/><circle cx="19.5" cy="11" r="2.2"/>',
        'leaf'      => '<path d="M11 20A7 7 0 0 1 9.8 6.1C15.5 5 17 4.5 19 2c1 2 2 4.2 2 8a7 7 0 0 1-7 7z"/><path d="M2 21c0-3 1.8-6.5 5-9"/>',

        // Service icons
        'brain'     => '<path d="M9.5 2A2.5 2.5 0 0 0 7 4.5v.6A3 3 0 0 0 4.5 8v.4A3 3 0 0 0 3 11a3 3 0 0 0 1.2 2.4A3 3 0 0 0 6 18.6a3 3 0 0 0 3.5 3.3V2.2A2.5 2.5 0 0 0 9.5 2z"/><path d="M14.5 2A2.5 2.5 0 0 1 17 4.5v.6A3 3 0 0 1 19.5 8v.4A3 3 0 0 1 21 11a3 3 0 0 1-1.2 2.4 3 3 0 0 1-1.8 5.2 3 3 0 0 1-3.5 3.3V2.2"/>',
        'heart-pulse' => '<path d="M19.5 12.6 12 20l-7.5-7.4a4.9 4.9 0 0 1 7-6.9l.5.5.5-.5a4.9 4.9 0 0 1 7 6.9z"/><polyline points="3.5 12.5 8 12.5 9.8 9.5 12.2 15 14 12.5 20.5 12.5"/>',
        'seedling'  => '<path d="M12 21v-8"/><path d="M12 13C12 9 9 6 5 6c0 4 3 7 7 7z"/><path d="M12 13c0-3.4 2.6-6 6-6 0 3.4-2.6 6-6 6z"/><path d="M8 21h8"/>',
        'stomach'   => '<path d="M9 3v4.5c0 2-1.2 2.8-2.6 3.6C4.6 12.2 3 13.5 3 16a5 5 0 0 0 5 5h4a8 8 0 0 0 8-8c0-3-1.6-4.6-3.4-4.6S13 9.8 13 11.6"/><path d="M7 3h4"/>',
        'strand'    => '<path d="M6 21c0-6 1-12 3-15"/><path d="M12 21c0-7 1.4-13 4-16"/><path d="M18 21c-.3-4 .2-7.5 1.5-10"/><path d="M4 21h16"/>',
        'joint'     => '<circle cx="12" cy="12" r="3.2"/><path d="M12 8.8V5a2.5 2.5 0 0 0-2.5-2.5h0A2.5 2.5 0 0 0 7 5v1"/><path d="M12 15.2V19a2.5 2.5 0 0 0 2.5 2.5h0A2.5 2.5 0 0 0 17 19v-1"/><path d="M8.8 12H5a2.5 2.5 0 0 1-2.5-2.5"/><path d="M15.2 12H19a2.5 2.5 0 0 1 2.5 2.5"/>',
        'skin'      => '<path d="M4 8a8 8 0 0 1 16 0v9a3 3 0 0 1-3 3H7a3 3 0 0 1-3-3z"/><circle cx="9" cy="10" r="1" fill="currentColor" stroke="none"/><circle cx="14.5" cy="13" r="1" fill="currentColor" stroke="none"/><circle cx="11" cy="16" r="1" fill="currentColor" stroke="none"/>',
        'allergy'   => '<circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3"/><path d="m4.9 4.9 2.2 2.2M16.9 16.9l2.2 2.2M19.1 4.9l-2.2 2.2M7.1 16.9l-2.2 2.2"/>',
        'thyroid'   => '<path d="M8 4v4.5a4 4 0 0 0 8 0V4"/><path d="M7.5 10.5C5.5 11.5 4.5 13.5 5 16c.5 2.5 2.5 4 4.5 3.5 1.6-.4 2.5-2 2.5-4"/><path d="M16.5 10.5c2 1 3 3 2.5 5.5-.5 2.5-2.5 4-4.5 3.5-1.6-.4-2.5-2-2.5-4"/>',
    ];

    $body = $paths[$name] ?? $paths['check'];
    $cls  = $class !== '' ? ' class="' . e($class) . '"' : '';

    return '<svg' . $cls . ' width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none"'
        . ' stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"'
        . ' aria-hidden="true" focusable="false">' . $body . '</svg>';
}
