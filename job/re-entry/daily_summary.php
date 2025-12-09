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

// ===========================
// ① 前日の不具合件数
// ===========================
$badYesterday = $service->getBadCountForDate($yesterday);

// ===========================
// ② 前日の再登録件数
// ===========================
$reentryYesterday = $service->getReentryCountForDate($yesterday);

// ===========================
// JSON 生成（前日のデータ）
// ===========================
$output = [
    'date'              => $yesterday,
    'bad_yesterday'     => $badYesterday,
    'reentry_yesterday' => $reentryYesterday
];

$outfile = __DIR__ . "/cache/daily_summary_{$yesterday}.json";
file_put_contents($outfile, json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo "OK: daily summary created → {$outfile}\n";
