<?php
declare(strict_types=1);

require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/../../job/re-entry/ReentryService.php';

date_default_timezone_set('Asia/Tokyo');

$pdo = new PDO(
    "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
    DB_USER,
    DB_PASSWORD,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$service = new ReentryService($pdo);
$list = $service->getNoReentryList();

$filename = "no_reentry_" . date('Ymd_His') . ".csv";

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename=' . $filename);

$fp = fopen('php://output', 'w');

fputcsv($fp, ['email','bad_member_id','bad_time']);

foreach ($list as $row) {
    fputcsv($fp, $row);
}

fclose($fp);
exit;
?>
