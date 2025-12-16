#!/usr/bin/env php
<?php
/**
 * entryテーブル全期間を週ごとCSV出力＋全データCSV出力（週1 cron 想定）
 * - 文字コード: SJIS-win
 * - 改行: CRLF
 * - Excel互換: member_id は ="..." で先頭ゼロ保持
 * - URL 列: APPLY_URL + '/' + HEX(public_token)（public_token が NULL の場合は空欄）
 * - 直接ファイルにストリーム書き込み（大規模データでも安定）
 * - 排他制御: .lock による多重起動防止
 */

declare(strict_types=1);

require_once __DIR__ . '/../../public_html/formapp/setting.php'; // DB定数 / APPLY_URL を定義しておく
require_once __DIR__ . '/../../../bin/vendor/autoload.php';             // ← PhpSpreadsheet 用 autoload

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

// ====== 共通設定 ======
$tz        = new DateTimeZone('Asia/Tokyo');
$exportDir = __DIR__ . '/../exports';
if (!is_dir($exportDir)) {
    $old = umask(0002);
    if (!@mkdir($exportDir, 0775, true) && !is_dir($exportDir)) {
        fwrite(STDERR, "[ERROR] cannot create dir: {$exportDir}\n");
        exit(1);
    }
    umask($old);
}

// 排他（多重起動防止）
$lockFp = fopen($exportDir . '/.lock', 'c');
if (!$lockFp || !flock($lockFp, LOCK_EX | LOCK_NB)) {
    echo "[WARN] Another exporter is running. Exit.\n";
    exit(0);
}

// ====== DB接続 ======
$dsn = sprintf(
    'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
    DB_HOST,
    DB_PORT,
    DB_NAME
);
$pdo = new PDO($dsn, DB_USER, DB_PASSWORD, [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
]);
$pdo->exec("SET NAMES utf8mb4");

// ====== 最古・最新のcreated_at取得 ======
$range = $pdo->query("SELECT MIN(created_at) AS min_date, MAX(created_at) AS max_date FROM entry")->fetch();
if (empty($range['min_date'])) {
    echo "No data found.\n";
    exit(0);
}
$minDate = new DateTime($range['min_date'], $tz);
$maxDate = new DateTime($range['max_date'], $tz);

// ====== 全データCSV ======
$allCsv = $exportDir . '/entry_all.csv';
exportCsvToFile($pdo, $allCsv, null, null);
echo "[OK] All data exported: {$allCsv}\n";

// ====== 全データXLSX（Excel用） ======
$allXlsx = $exportDir . '/entry_all.xlsx';
exportXlsxToFile($pdo, $allXlsx, null, null);
echo "[OK] All data XLSX exported: {$allXlsx}\n";

// ====== 週次CSV（最新→過去、月曜始まり〜日曜終わりの週単位） ======
$current = (clone $maxDate)->modify('sunday this week')->setTime(23, 59, 59);
while ($current >= $minDate) {
    $end   = clone $current;
    $start = (clone $end)->modify('monday this week')->setTime(0, 0, 0);
    if ($start < $minDate) { $start = clone $minDate; }

    $filename = sprintf('%s/entry_%s_%s.csv',
        $exportDir,
        $start->format('Ymd'),
        $end->format('Ymd')
    );
    exportCsvToFile($pdo, $filename, $start, $end);
    echo "[OK] Week exported: {$filename}\n";

    // 週次 XLSX
    $xlsxFilename = sprintf('%s/entry_%s_%s.xlsx',
        $exportDir,
        $start->format('Ymd'),
        $end->format('Ymd')
    );
    exportXlsxToFile($pdo, $xlsxFilename, $start, $end);
    echo "[OK] Week XLSX exported: {$xlsxFilename}\n";

    // 1週間戻す
    $current->modify('-7 days');
}

// ---- ここから関数群 ----

/**
 * 期間指定で CSV を直接ファイルに書き出す（原子的置き換え）
 * - member_id が 16桁数字のみ
 * - created_at を対象
 */
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

    // APPLY_URL（未定義なら空文字）
    $base = defined('APPLY_URL') ? rtrim((string)APPLY_URL, '/') : '';

    // ===== ディレクトリ作成 =====
    $dir = dirname($filepath);
    if (!is_dir($dir)) {
        $old = umask(0002);
        if (!@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException("cannot create dir: {$dir}");
        }
        umask($old);
    }

    // ===== 一時ファイルに書き込み =====
    $tmp = $filepath . '.tmp.' . getmypid() . '.' . bin2hex(random_bytes(4));
    $out = @fopen($tmp, 'wb');
    if (!$out) {
        throw new RuntimeException("cannot open temp file: {$tmp}");
    }

    // ===== CSV 1行書き込み関数 =====
    $writeCsvLine = function (array $fields) use ($out): void {
        $mem = fopen('php://temp', 'r+');
        fputcsv($mem, $fields);
        rewind($mem);
        $line = stream_get_contents($mem) ?: '';
        fclose($mem);

        // CRLF に統一
        $line = rtrim($line, "\n") . "\r\n";

        // SJIS-win 変換して書き込み
        $sjis = mb_convert_encoding($line, 'SJIS-win', 'UTF-8');
        fwrite($out, $sjis);
    };

    // ===== CSV ヘッダ =====
    $writeCsvLine(['member_id', 'present', 'email', 'token', 'url', 'created_at']);

    // ===== 本文 =====
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {

        $tokenHex = $r['public_token_hex'] ?? null;

        if ($tokenHex !== null && $tokenHex !== '') {
            $tokenHexLower = strtolower($tokenHex);
            $url = ($base !== '' ? $base . '/' . $tokenHexLower : '');
        } else {
            $tokenHexLower = '';
            $url = '';
        }

        $writeCsvLine([
            $r['member_id'],
            (string)$r['present'],
            (string)$r['email'],
            $tokenHexLower,
            $url,
            (string)$r['created_at'],
        ]);
    }

    fclose($out);
    @chmod($tmp, 0664);

    // ===== 本番に rename（原子的置き換え） =====
    if (!@rename($tmp, $filepath)) {
        @unlink($tmp);
        throw new RuntimeException("failed to place CSV (rename): {$filepath}");
    }
}


/**
 * 期間指定で XLSX を出力
 * - CSV と同じ条件で SELECT
 * - member_id / token / url / created_at は文字列として保存
 *   → 16桁でも先頭ゼロ・桁数・日付変換などを完全に防ぐ
 */
/**
 * 期間指定で XLSX を出力
 * - member_id が 16桁数字のみ
 * - created_at を対象
 */
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

    // APPLY_URL（未定義なら空文字）
    $base = defined('APPLY_URL') ? rtrim((string)APPLY_URL, '/') : '';

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('entry');

    // ===== ヘッダ =====
    $sheet->fromArray(
        ['member_id', 'present', 'email', 'token', 'url', 'created_at'],
        null,
        'A1'
    );

    $rowNum = 2;

    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $memberId = (string)$r['member_id'];
        $present  = (string)$r['present'];
        $email    = (string)$r['email'];

        $tokenHex = $r['public_token_hex'] ?? null;

        if ($tokenHex !== null && $tokenHex !== '') {
            $tokenHexLower = strtolower($tokenHex);
            $url = ($base !== '' ? $base . '/' . $tokenHexLower : '');
        } else {
            $tokenHexLower = '';
            $url = '';
        }

        // Excel の自動型変換を防ぐためすべて文字列
        $sheet->setCellValueExplicit("A{$rowNum}", $memberId, DataType::TYPE_STRING);
        $sheet->setCellValueExplicit("B{$rowNum}", $present,  DataType::TYPE_STRING);
        $sheet->setCellValueExplicit("C{$rowNum}", $email,    DataType::TYPE_STRING);
        $sheet->setCellValueExplicit("D{$rowNum}", $tokenHexLower, DataType::TYPE_STRING);
        $sheet->setCellValueExplicit("E{$rowNum}", $url,      DataType::TYPE_STRING);
        $sheet->setCellValueExplicit("F{$rowNum}", (string)$r['created_at'], DataType::TYPE_STRING);

        $rowNum++;
    }

    // ===== ディレクトリ作成 =====
    $dir = dirname($filepath);
    if (!is_dir($dir)) {
        $old = umask(0002);
        if (!@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException("cannot create dir: {$dir}");
        }
        umask($old);
    }

    // ===== 保存 =====
    $writer = new Xlsx($spreadsheet);
    $writer->save($filepath);
    @chmod($filepath, 0664);
}
