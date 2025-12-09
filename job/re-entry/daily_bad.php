<?php
declare(strict_types=1);

date_default_timezone_set('Asia/Tokyo');

require_once __DIR__ . '/../../public_html/re-entry/settings.php';
require_once __DIR__ . '/ReentryService.php';

// --- DB接続 ---
$pdo = new PDO(
    "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
    DB_USER,
    DB_PASSWORD,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$service = new ReentryService($pdo);

$today = date('Y-m-d');

// --- 12/6〜全件（ReentryService は今日分も返すので前日までに絞る）---
$all = $service->getBadDailySince1206();

// 前日までを抽出
$beforeToday = array_filter($all, function ($row) use ($today) {
    return $row['day'] < $today;
});

// 保存フォルダ
$savePath = __DIR__ . '/cache/bad_daily.json';

// JSON化
file_put_contents(
    $savePath,
    json_encode(['days' => array_values($beforeToday)], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
);

echo "daily_bad OK\n";
