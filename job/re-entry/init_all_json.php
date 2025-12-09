<?php
declare(strict_types=1);
date_default_timezone_set('Asia/Tokyo');

require_once __DIR__ . '/ReentryService.php';
require_once __DIR__ . '/../../public_html/re-entry/settings.php';

$pdo = new PDO(
    "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
    DB_USER,
    DB_PASSWORD,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$service = new ReentryService($pdo);

$cacheDir = __DIR__ . '/cache';
if (!is_dir($cacheDir)) {
    mkdir($cacheDir, 0777, true);
}

$today     = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));

// -------------------------------------------------------------
// ① 前日サマリー (daily_summary_YYYY-MM-DD.json)
// -------------------------------------------------------------
$badYesterday     = $service->getBadYesterday();
$reentryYesterday = $service->getReentryYesterday();

file_put_contents(
    "{$cacheDir}/daily_summary_{$yesterday}.json",
    json_encode([
        "date"              => $yesterday,
        "bad_yesterday"     => $badYesterday,
        "reentry_yesterday" => $reentryYesterday
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
    LOCK_EX
);

// -------------------------------------------------------------
// ② 夜間不具合 (bad_night.json)
// -------------------------------------------------------------
$badNight = $service->getBadNightForDate($today);
file_put_contents(
    "{$cacheDir}/bad_night.json",
    json_encode(["count" => $badNight], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
    LOCK_EX
);

// -------------------------------------------------------------
// ③ 昼間不具合 (bad_daytime.json)
// -------------------------------------------------------------
$badDaytime = $service->getBadDaytimeForDate($today);
file_put_contents(
    "{$cacheDir}/bad_daytime.json",
    json_encode(["count" => $badDaytime], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
    LOCK_EX
);

// -------------------------------------------------------------
// ④ 累計再登録（9:30）
// -------------------------------------------------------------
$re930 = $service->getReentryTotalUntil("09:30");
file_put_contents(
    "{$cacheDir}/reentry_total_0930.json",
    json_encode(["date" => $today, "count" => $re930], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
    LOCK_EX
);

// -------------------------------------------------------------
// ⑤ 累計再登録（18:00）
// -------------------------------------------------------------
$re1800 = $service->getReentryTotalUntil("18:00");
file_put_contents(
    "{$cacheDir}/reentry_total_1800.json",
    json_encode(["date" => $today, "count" => $re1800], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
    LOCK_EX
);

// -------------------------------------------------------------
// ⑥ 日別不具合 (bad_daily.json) — 12/6 以降を取得
// -------------------------------------------------------------
$badDaily = $service->getBadDailySince1206();
file_put_contents(
    "{$cacheDir}/bad_daily.json",
    json_encode(["days" => $badDaily], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
    LOCK_EX
);

echo "ALL JSON created successfully.\n";
