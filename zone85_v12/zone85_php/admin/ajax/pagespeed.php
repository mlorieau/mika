<?php
// admin/ajax/pagespeed.php — Proxy Google PageSpeed Insights API
// Called via GET ?url=ENCODED_URL from admin SEO panels

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/admin.php';
require_admin();

header('Content-Type: application/json; charset=utf-8');

$url = trim($_GET['url'] ?? '');
if (!$url || !filter_var($url, FILTER_VALIDATE_URL)) {
    echo json_encode(['error' => 'URL invalide']);
    exit;
}

// Cache in session (5-minute TTL per URL)
$cache_key = 'ps_' . md5($url);
if (isset($_SESSION[$cache_key]) && (time() - ($_SESSION[$cache_key]['ts'] ?? 0)) < 300) {
    echo json_encode($_SESSION[$cache_key]['data']);
    exit;
}

function ps_fetch(string $url, string $strategy): ?array
{
    $api = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed?url='
         . urlencode($url)
         . '&strategy=' . $strategy;

    $ctx = stream_context_create(['http' => [
        'timeout'        => 20,
        'ignore_errors'  => true,
        'user_agent'     => 'Zone85-Admin/1.0',
    ]]);

    $raw = @file_get_contents($api, false, $ctx);
    if ($raw === false) return null;

    $data = json_decode($raw, true);
    if (!is_array($data)) return null;

    return $data;
}

$mobile  = ps_fetch($url, 'mobile');
$desktop = ps_fetch($url, 'desktop');

if ($mobile === null && $desktop === null) {
    echo json_encode(['error' => 'Impossible de joindre l\'API PageSpeed. Vérifier la connectivité.']);
    exit;
}

function extract_score(?array $data): float
{
    return (float)($data['lighthouseResult']['categories']['performance']['score'] ?? 0);
}

function extract_cwv(?array $data): array
{
    if (!$data) return [];
    $audits = $data['lighthouseResult']['audits'] ?? [];
    $cwv    = [];

    $map = [
        'lcp'  => 'largest-contentful-paint',
        'cls'  => 'cumulative-layout-shift',
        'fcp'  => 'first-contentful-paint',
        'ttfb' => 'server-response-time',
        'inp'  => 'interaction-to-next-paint',
    ];

    foreach ($map as $key => $audit_key) {
        if (isset($audits[$audit_key]['displayValue'])) {
            $cwv[$key] = $audits[$audit_key]['displayValue'];
        }
    }

    return $cwv;
}

$result = [
    'mobile'  => extract_score($mobile),
    'desktop' => extract_score($desktop),
    'cwv'     => extract_cwv($mobile),
    'url'     => $url,
    'ts'      => date('H:i'),
];

// Cache result
$_SESSION[$cache_key] = ['ts' => time(), 'data' => $result];

echo json_encode($result);
