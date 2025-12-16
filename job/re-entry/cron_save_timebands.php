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

/*=================================================================
    morning（09:30）：
    - 前日18:00〜当日9:30 → night
    - 09:30 時点の累計
=================================================================*/
if ($mode === 'morning') {

    /* 夜間不具合数 → bad_night.json */
    $badNight = $service->getBadNightForDate($today);

    $fileNight = $cacheDir . "/bad_night.json";
    $jsonNight = [
        "date"  => $today,
        "count" => $badNight
    ];
    file_put_contents(
        $fileNight,
        json_encode($jsonNight, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
        LOCK_EX
    );

    /* 9:30 再登録累計 → reentry_total_0930.json */
    $count930 = $service->getReentryTotalUntil("09:30");

    $file930 = $cacheDir . "/reentry_total_0930.json";
    $json930 = [
        "date"  => $today,
        "count" => $count930
    ];
    file_put_contents(
        $file930,
        json_encode($json930, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
        LOCK_EX
    );

    echo "Executed morning mode\n";
    echo "Saved: bad_night.json\n";
    echo "Saved: reentry_total_0930.json\n";
}


/*=================================================================
    evening（18:00）：
    - 当日9:30〜18:00 → daytime
    - 18:00 時点の累計
=================================================================*/
if ($mode === 'evening') {

    /* 昼間不具合数 → bad_daytime.json */
    $badDaytime = $service->getBadDaytimeForDate($today);

    $fileDay = $cacheDir . "/bad_daytime.json";
    $jsonDay = [
        "date"  => $today,
        "count" => $badDaytime
    ];
    file_put_contents(
        $fileDay,
        json_encode($jsonDay, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
        LOCK_EX
    );

    /* 18:00 再登録累計 → reentry_total_1800.json */
    $count1800 = $service->getReentryTotalUntil("18:00");

    $file1800 = $cacheDir . "/reentry_total_1800.json";
    $json1800 = [
        "date"  => $today,
        "count" => $count1800
    ];
    file_put_contents(
        $file1800,
        json_encode($json1800, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
        LOCK_EX
    );

    echo "Executed evening mode\n";
    echo "Saved: bad_daytime.json\n";
    echo "Saved: reentry_total_1800.json\n";
}

