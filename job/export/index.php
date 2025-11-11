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

// ====== 最古・最新のupdated_at取得 ======
$range = $pdo->query("SELECT MIN(updated_at) AS min_date, MAX(updated_at) AS max_date FROM entry")->fetch();
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

    // 1週間戻す
    $current->modify('-7 days');
}

// ---- ここから関数群 ----

/**
 * 期間指定で CSV を直接ファイルに書き出す（原子的置き換え）
 * - public_token は VARBINARY(16) を HEX(32桁) にして URL 化
 * - APPLY_URL は setting.php で定義（例: 'https://example.com/apply'）
 */
function exportCsvToFile(PDO $pdo, string $filepath, ?DateTime $start, ?DateTime $end): void
{
    $sql = "
        SELECT
            member_id,
            present,
            email,
            CASE WHEN public_token IS NULL THEN NULL ELSE HEX(public_token) END AS public_token_hex,
            updated_at
        FROM entry
    ";
    $params = [];
    if ($start && $end) {
        $sql .= " WHERE updated_at BETWEEN :start AND :end";
        $params[':start'] = $start->format('Y-m-d H:i:s');
        $params[':end']   = $end->format('Y-m-d H:i:s');
    }
    $sql .= " ORDER BY updated_at ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    // ベースURL（未定義なら空文字でURL生成をスキップ）
    $base = defined('APPLY_URL') ? rtrim((string)APPLY_URL, '/') : '';

    // 原子的置き換えのため一時ファイルへ書いてから rename
    $dir = dirname($filepath);
    if (!is_dir($dir)) {
        $old = umask(0002);
        if (!@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException("cannot create dir: {$dir}");
        }
        umask($old);
    }
    $tmp = $filepath . '.tmp.' . getmypid() . '.' . bin2hex(random_bytes(4));
    $out = @fopen($tmp, 'wb');
    if (!$out) {
        throw new RuntimeException("cannot open temp file: {$tmp}");
    }

    // 1行書き込み: UTF-8配列 -> CSV行 -> CRLF -> SJIS-win 変換 -> 書き込み
    $writeCsvLine = function (array $fields) use ($out): void {
        $mem = fopen('php://temp', 'r+');
        fputcsv($mem, $fields);          // 行末は "\n"
        rewind($mem);
        $line = stream_get_contents($mem) ?: '';
        fclose($mem);

        // CRLF に統一
        $line = rtrim($line, "\n") . "\r\n";

        // SJIS-win へ変換して出力
        $sjis = mb_convert_encoding($line, 'SJIS-win', 'UTF-8');
        fwrite($out, $sjis);
    };

    // ヘッダ
    $writeCsvLine(['member_id', 'present', 'email', 'url', 'updated_at']);

    // 本文
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Excelで先頭ゼロを保持
        $memberIdExcel = '="' . (string)$r['member_id'] . '"';

        // URL を生成（public_token が無い場合は空欄）
        $tokenHex = $r['public_token_hex'] ?? null;
        $url = ($base !== '' && $tokenHex) ? $base . '/' . strtolower($tokenHex) : '';

        $writeCsvLine([
            $memberIdExcel,
            (string)$r['present'],
            (string)$r['email'],
            $url,
            (string)$r['updated_at'],
        ]);
    }

    fclose($out);
    @chmod($tmp, 0664);
    if (!@rename($tmp, $filepath)) {
        @unlink($tmp);
        throw new RuntimeException("failed to place CSV (rename): {$filepath}");
    }
}
