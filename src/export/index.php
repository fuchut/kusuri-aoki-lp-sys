#!/usr/bin/env php
<?php
/**
 * entryテーブル全期間を週ごとCSV出力＋全データCSV出力
 * Excel対応（SJIS-win / 先頭ゼロ保持）
 */

require_once __DIR__ . '/../formapp/setting.php';

// ====== 共通設定 ======
$tz = new DateTimeZone('Asia/Tokyo');
$exportDir = __DIR__ . '/../exports';
if (!is_dir($exportDir)) mkdir($exportDir, 0775, true);

$dsn = sprintf(
    'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
    DB_HOST,
    DB_PORT,
    DB_NAME
);
$pdo = new PDO($dsn, DB_USER, DB_PASSWORD, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

// ====== 最古・最新のupdated_at取得 ======
$rangeSql = "SELECT MIN(updated_at) AS min_date, MAX(updated_at) AS max_date FROM entry";
$range = $pdo->query($rangeSql)->fetch();
if (!$range['min_date']) {
    echo "No data found.\n";
    exit;
}

$minDate = new DateTime($range['min_date'], $tz);
$maxDate = new DateTime($range['max_date'], $tz);

// ====== 全データCSV ======
$allCsv = $exportDir . '/entry_all.csv';
exportCsv($pdo, $allCsv, null, null);
echo "[OK] All data exported: {$allCsv}\n";

// ====== 週次CSV（最新→過去） ======
$current = clone $maxDate;
// 最終週の日曜まで合わせる
$current->modify('sunday this week')->setTime(23,59,59);

while ($current >= $minDate) {
    $end = clone $current;
    $start = (clone $end)->modify('monday this week')->setTime(0,0,0);
    if ($start < $minDate) $start = clone $minDate;

    $filename = sprintf('%s/entry_%s_%s.csv',
        $exportDir,
        $start->format('Ymd'),
        $end->format('Ymd')
    );
    exportCsv($pdo, $filename, $start, $end);
    echo "[OK] Week exported: {$filename}\n";

    // 1週間前へ
    $current->modify('-7 days');
}

// ====== 関数定義 ======
function exportCsv($pdo, $filename, $start = null, $end = null)
{
    $sql = "SELECT member_id, present, email, updated_at FROM entry";
    $params = [];
    if ($start && $end) {
        $sql .= " WHERE updated_at BETWEEN :start AND :end ORDER BY updated_at ASC";
        $params = [
            ':start' => $start->format('Y-m-d H:i:s'),
            ':end'   => $end->format('Y-m-d H:i:s'),
        ];
    } else {
        $sql .= " ORDER BY updated_at ASC";
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    // メモリ上にUTF-8で書き込み
    $tmp = fopen('php://temp', 'r+');
    fputcsv($tmp, ['member_id','present','email','updated_at']);
    while ($r = $stmt->fetch()) {
        $memberId = '="' . (string)$r['member_id'] . '"';
        fputcsv($tmp, [
            $memberId,
            (string)$r['present'],
            (string)$r['email'],
            (string)$r['updated_at']
        ]);
    }
    rewind($tmp);
    $utf8 = stream_get_contents($tmp);
    fclose($tmp);

    // 改行CRLF＋SJIS-win変換
    $utf8 = str_replace("\n", "\r\n", $utf8);
    $sjis = mb_convert_encoding($utf8, 'SJIS-win', 'UTF-8');
    file_put_contents($filename, $sjis);
    chmod($filename, 0664);
}
