#!/usr/bin/env php
<?php
/**
 * exports ディレクトリ内の entry_*（CSV/XLSX）を削除し、
 * DB から再度「全データ」と「週ごと」の CSV/XLSX を再生成する。
 * 対象データは member_id が「16桁の数字のみ」。
 */

declare(strict_types=1);

require_once __DIR__ . '/../../public_html/formapp/setting.php';
require_once __DIR__ . '/../../../bin/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

// ===== 共通設定 =====
$tz        = new DateTimeZone('Asia/Tokyo');
$exportDir = __DIR__ . '/../exports';

// ===== 出力先フォルダ確認 / 作成 =====
if (!is_dir($exportDir)) {
    mkdir($exportDir, 0775, true);
}

// ===== 既存ファイル削除 =====
echo "[INFO] Remove old export files...\n";
$files = glob($exportDir . '/entry_*');
foreach ($files as $f) {
    @unlink($f);
}
echo "[OK] Old files removed.\n";

// ===== DB 接続 =====
$dsn = sprintf(
    'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
    DB_HOST,
    DB_PORT,
    DB_NAME
);

$pdo = new PDO($dsn, DB_USER, DB_PASSWORD, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

// ===== 対象データ期間取得（member_id が 16桁のみ） =====
$range = $pdo->query("
    SELECT MIN(created_at) AS min_date,
           MAX(created_at) AS max_date
    FROM entry
    WHERE member_id REGEXP '^[0-9]{16}$'
")->fetch();

if (!$range['min_date']) {
    echo "[WARN] entry テーブルに該当データ（16桁 member_id）がありません\n";
    exit(0);
}

$minDate = new DateTime($range['min_date'], $tz);
$maxDate = new DateTime($range['max_date'], $tz);

echo "[INFO] Data range: {$minDate->format('Y-m-d')} → {$maxDate->format('Y-m-d')}\n";

// ===== 全データ出力 =====
echo "[INFO] Export ALL data...\n";

$allCsv  = $exportDir . '/entry_all.csv';
$allXlsx = $exportDir . '/entry_all.xlsx';

exportCsvToFile($pdo, $allCsv, null, null);
exportXlsxToFile($pdo, $allXlsx, null, null);

echo "[OK] ALL data exported.\n";

// ===== 週次出力（最新週から過去へ） =====
echo "[INFO] Export WEEKLY data...\n";

$current = (clone $maxDate)->modify('sunday this week')->setTime(23, 59, 59);

while ($current >= $minDate) {
    $end   = clone $current;
    $start = (clone $end)->modify('monday this week')->setTime(0, 0, 0);

    if ($start < $minDate) {
        $start = clone $minDate;
    }

    $csv  = sprintf('%s/entry_%s_%s.csv',  $exportDir, $start->format('Ymd'), $end->format('Ymd'));
    $xlsx = sprintf('%s/entry_%s_%s.xlsx', $exportDir, $start->format('Ymd'), $end->format('Ymd'));

    exportCsvToFile($pdo, $csv,  $start, $end);
    exportXlsxToFile($pdo, $xlsx, $start, $end);

    echo "[OK] Week exported: {$start->format('Ymd')} - {$end->format('Ymd')}\n";

    $current->modify('-7 days');
}

echo "\n===== REBUILD COMPLETED =====\n";
exit(0);


/* ============================================================
   CSV 出力関数（16桁 member_id 条件 / created_at 対応）
   ============================================================ */
function exportCsvToFile(PDO $pdo, string $filepath, ?DateTime $start, ?DateTime $end): void
{
    $sql = "
        SELECT
            member_id,
            present,
            email,
            CASE WHEN public_token IS NULL THEN NULL ELSE HEX(public_token) END AS public_token_hex,
            created_at
        FROM entry
        WHERE member_id REGEXP '^[0-9]{16}$'
    ";

    $params = [];

    if ($start && $end) {
        $sql .= " AND created_at BETWEEN :start AND :end";
        $params[':start'] = $start->format('Y-m-d H:i:s');
        $params[':end']   = $end->format('Y-m-d H:i:s');
    }

    $sql .= " ORDER BY created_at ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $base = defined('APPLY_URL') ? rtrim(APPLY_URL, '/') : '';

    $tmp = $filepath . '.tmp.' . uniqid();
    $out = fopen($tmp, 'wb');

    // 書き込み関数
    $writeCsv = function (array $fields) use ($out) {
        $mem = fopen('php://temp', 'r+');
        fputcsv($mem, $fields);
        rewind($mem);
        $line = stream_get_contents($mem);
        fclose($mem);

        $line = rtrim($line, "\n") . "\r\n";
        fwrite($out, mb_convert_encoding($line, 'SJIS-win', 'UTF-8'));
    };

    // ヘッダ
    $writeCsv(['member_id', 'present', 'email', 'token', 'url', 'created_at']);

    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $memberIdExcel = '="' . $r['member_id'] . '"';

        $tokenHex = $r['public_token_hex'] ?? null;

        if ($tokenHex) {
            $tokenLower = strtolower($tokenHex);
            $tokenExcel = '="' . $tokenLower . '"';
            $url        = ($base ? $base . '/' . $tokenLower : '');
        } else {
            $tokenExcel = '';
            $url        = '';
        }

        $writeCsv([
            $memberIdExcel,
            $r['present'],
            $r['email'],
            $tokenExcel,
            $url,
            $r['created_at'],
        ]);
    }

    fclose($out);
    rename($tmp, $filepath);
    chmod($filepath, 0664);
}


/* ============================================================
   XLSX 出力関数（16桁 member_id 条件 / created_at 対応）
   ============================================================ */
function exportXlsxToFile(PDO $pdo, string $filepath, ?DateTime $start, ?DateTime $end): void
{
    $sql = "
        SELECT
            member_id,
            present,
            email,
            CASE WHEN public_token IS NULL THEN NULL ELSE HEX(public_token) END AS public_token_hex,
            created_at
        FROM entry
        WHERE member_id REGEXP '^[0-9]{16}$'
    ";

    $params = [];

    if ($start && $end) {
        $sql .= " AND created_at BETWEEN :start AND :end";
        $params[':start'] = $start->format('Y-m-d H:i:s');
        $params[':end']   = $end->format('Y-m-d H:i:s');
    }

    $sql .= " ORDER BY created_at ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $base = defined('APPLY_URL') ? rtrim(APPLY_URL, '/') : '';

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('entry');

    // ヘッダ
    $sheet->fromArray(
        ['member_id', 'present', 'email', 'token', 'url', 'created_at'],
        null,
        'A1'
    );

    $row = 2;

    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $token = $r['public_token_hex'] ? strtolower($r['public_token_hex']) : '';
        $url   = ($token && $base) ? $base . '/' . $token : '';

        $sheet->setCellValueExplicit("A{$row}", (string)$r['member_id'], DataType::TYPE_STRING);
        $sheet->setCellValueExplicit("B{$row}", (string)$r['present'],    DataType::TYPE_STRING);
        $sheet->setCellValueExplicit("C{$row}", (string)$r['email'],      DataType::TYPE_STRING);
        $sheet->setCellValueExplicit("D{$row}", $token,                   DataType::TYPE_STRING);
        $sheet->setCellValueExplicit("E{$row}", $url,                     DataType::TYPE_STRING);
        $sheet->setCellValueExplicit("F{$row}", (string)$r['created_at'], DataType::TYPE_STRING);

        $row++;
    }

    $writer = new Xlsx($spreadsheet);
    $writer->save($filepath);
    chmod($filepath, 0664);
}
