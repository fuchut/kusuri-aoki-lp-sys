<?php
declare(strict_types=1);

date_default_timezone_set('Asia/Tokyo');

require_once __DIR__ . '/../../public_html/re-entry/settings.php';
require_once __DIR__ . '/ReentryService.php';

// ===== DB接続 =====
$pdo = new PDO(
    "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
    DB_USER,
    DB_PASSWORD,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$service = new ReentryService($pdo);

$yesterday = date('Y-m-d', strtotime('-1 day'));
$cacheDir  = __DIR__ . "/cache";
if (!is_dir($cacheDir)) {
    mkdir($cacheDir, 0777, true);
}


/* ============================================================
   ① 前日の不具合件数（1日分）
============================================================ */
$badYesterday = $service->getBadCountForDate($yesterday);


/* ============================================================
   ② 前日の再登録件数（1日分）
============================================================ */
$reentryYesterday = $service->getReentryCountForDate($yesterday);


/* ============================================================
   ③ daily_summary_yyyy-mm-dd.json に保存
============================================================ */
$summaryOut = [
    'date'              => $yesterday,
    'bad_yesterday'     => $badYesterday,
    'reentry_yesterday' => $reentryYesterday
];

$summaryFile = $cacheDir . "/daily_summary_{$yesterday}.json";
file_put_contents(
    $summaryFile,
    json_encode($summaryOut, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
);


/* ============================================================
   ④ bad_daily.json を更新（追加 or 初期作成）
============================================================ */
$badDailyFile = $cacheDir . "/bad_daily.json";

$badDaily = [
    "days" => []
];

// 既存ファイルのロード
if (file_exists($badDailyFile)) {
    $json = json_decode(file_get_contents($badDailyFile), true);
    if (isset($json["days"]) && is_array($json["days"])) {
        $badDaily["days"] = $json["days"];
    }
}

// 昨日のデータが既に存在するかチェック（=== で厳密比較）
$exists = false;
foreach ($badDaily["days"] as $row) {
    if (isset($row["day"]) && $row["day"] === $yesterday) {
        $exists = true;
        break;
    }
}

// ★ 0件でも必ず追加したい → 存在しなければ追加
if (!$exists) {
    $badDaily["days"][] = [
        "day"       => $yesterday,
        "bad_count" => $badYesterday  // ← 0 でもそのまま保存される
    ];
}

// 日付順にソート
usort($badDaily["days"], function ($a, $b) {
    return strcmp($a["day"], $b["day"]);
});

// 保存
file_put_contents(
    $badDailyFile,
    json_encode($badDaily, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
);

echo "OK: daily summary + bad_daily updated\n";
echo "  → {$summaryFile}\n";
echo "  → {$badDailyFile}\n";
