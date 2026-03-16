<?php
/**
 * Marlow FM Photobooth - Gallery Photos API
 * Returns a paginated, filterable, sortable list of all photos.
 *
 * GET params:
 *   page       int   (default 1)
 *   per_page   int   (default 24, max 48)
 *   sort       string  date_desc | date_asc | show  (default date_desc)
 *   show       string  filter by show name (optional)
 */

// Prevent browser caching - gallery must always show latest photos
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once '/home/marlowfm/photobooth-config/config.php';

$page       = max(1, (int)($_GET['page']     ?? 1));
$perPage    = min(48, max(1, (int)($_GET['per_page'] ?? 24)));
$sort       = in_array($_GET['sort'] ?? '', ['date_asc', 'show']) ? $_GET['sort'] : 'date_desc';
$filterShow = trim($_GET['show'] ?? '');

// ── Load metadata ─────────────────────────────────────────────────────────
$metadataFile = PHOTO_BASE_DIR . '/.metadata.json';
$allMetadata  = [];
if (file_exists($metadataFile)) {
    $allMetadata = json_decode(file_get_contents($metadataFile), true) ?? [];
}

// ── Build photo list ──────────────────────────────────────────────────────
$photos     = [];
$showCounts = [];

foreach ($allMetadata as $token => $meta) {
    if (!empty($meta['deleted'])) continue;
    $created   = $meta['created'] ?? '';
    if (!$created) continue;

    $datePath    = date('Y/m/d', strtotime($created));
    $brandedFile = $meta['filename_branded'] ?? '';
    $filePath    = PHOTO_BASE_DIR . '/' . $datePath . '/' . $brandedFile;

    if (!file_exists($filePath)) continue;

    $show = $meta['show'] ?? 'Unknown';
    $showCounts[$show] = ($showCounts[$show] ?? 0) + 1;

    if ($filterShow !== '' && $show !== $filterShow) continue;

    // Consolidate people field (new style) with legacy presenter/guests
    $people = $meta['people'] ?? '';
    if (!$people) {
        $parts  = array_filter([$meta['presenter'] ?? '', $meta['guests'] ?? '']);
        $people = implode(', ', $parts);
    }

    $thumbPath = $datePath . '/' . $brandedFile;

    $photos[] = [
        'token'       => $token,
        'title'       => $meta['title'] ?? '',
        'show'        => $show,
        'people'      => $people,
        'date_label'  => date('d M Y', strtotime($created)),
        'time'        => date('H:i', strtotime($created)),
        'thumb_url'   => '/photobooth/thumbs.php?path=' . rawurlencode($thumbPath) . '&w=320',
        'full_url'    => '/photobooth/photos/' . $thumbPath,
        'qr_url'      => 'https://photobooth.marlowfm.co.uk:8444/download.php?token=' . $token,
        '_ts'         => strtotime($created),   // used for sort, stripped below
    ];
}

// ── Sort ──────────────────────────────────────────────────────────────────
usort($photos, function ($a, $b) use ($sort) {
    if ($sort === 'date_asc') return $a['_ts'] - $b['_ts'];
    if ($sort === 'show')     return strcmp($a['show'], $b['show']) ?: $b['_ts'] - $a['_ts'];
    return $b['_ts'] - $a['_ts']; // date_desc (default)
});

// ── Paginate ──────────────────────────────────────────────────────────────
$total  = count($photos);
$pages  = max(1, (int) ceil($total / $perPage));
$page   = min($page, $pages);
$offset = ($page - 1) * $perPage;

$paginated = array_slice($photos, $offset, $perPage);

// Strip internal sort key
foreach ($paginated as &$p) unset($p['_ts']);
unset($p);

// ── Build shows list sorted by frequency desc ─────────────────────────────
arsort($showCounts);
$shows = array_keys($showCounts);

echo json_encode([
    'photos'      => $paginated,
    'total'       => $total,
    'page'        => $page,
    'per_page'    => $perPage,
    'pages'       => $pages,
    'shows'       => $shows,
    'sort'        => $sort,
    'filter_show' => $filterShow,
], JSON_UNESCAPED_UNICODE);
