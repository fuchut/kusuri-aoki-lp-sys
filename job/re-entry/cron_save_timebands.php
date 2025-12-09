<?php
declare(strict_types=1);
date_default_timezone_set('Asia/Tokyo');

require_once __DIR__ . '/../../public_html/re-entry/settings.php';
require_once __DIR__ . '/ReentryService.php';

$mode = $argv[1] ?? null;

if (!in_array($mode, ['morning', 'evening'], true)) {
    echo "ERROR: モード指定が不正です。morning / evening を指定してください。\n";
    exit(1);
}

$pdo = new PDO(
    "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
    DB_USER,
    DB_PASSWORD,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$service = new ReentryService($pdo);

$today = date('Y-m-d');

$cacheDir = __DIR__ . '/cache';
if (!is_dir($cacheDir)) {
    mkdir($cacheDir, 0777, true);
}

$summaryFile = $cacheDir . "/daily_summary_{$today}.json";

/* 既存データ読み込み（あれば） */
$data = [
    "date" => $today,
    "bad_night" => null,
    "bad_daytime" => null,
    "reentry_total_0930" => null,
    "reentry_total_1800" => null
];

if (file_exists($summaryFile)) {
    $json = json_decode(file_get_contents($summaryFile), true);
    if (is_array($json)) {
        $data = array_merge($data, $json);
    }
}

/*=================================================================
    morning（09:30）：前日18:00〜当日9:30 の夜間集計
=================================================================*/
if ($mode === 'morning') {

    $data["bad_night"] = $service->getBadNightForDate($today);
    $data["reentry_total_0930"] = $service->getReentryTotalUntil("09:30");

    echo "Executed morning mode\n";
}

/*=================================================================
    evening（18:00）：当日9:30〜18:00 の昼間集計
=================================================================*/
if ($mode === 'evening') {

    $data["bad_daytime"] = $service->getBadDaytimeForDate($today);
    $data["reentry_total_1800"] = $service->getReentryTotalUntil("18:00");

    echo "Executed evening mode\n";
}

/*=================================================================
    JSON 保存
=================================================================*/
file_put_contents(
    $summaryFile,
    json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
    LOCK_EX
);

echo "Saved: {$summaryFile}\n";
