<?php
/**
 * compare_and_export.php
 * 2つのCSVを比較し、片方にしか存在しないメールアドレスをCSV保存
 */

// ====== 設定 ======
$csvA = __DIR__ . '/reentry_20251211_190947.csv';
$csvB = __DIR__ . '/reentry_20251212_103101.csv';

$colA = 0; // A: 1列目（0-index）
$colB = 0; // B: 3列目

$outputA = __DIR__ . '/only_a.csv';
$outputB = __DIR__ . '/only_b.csv';

// ===== CSV 読み込み関数 =====
function loadCsvEmails(string $path, int $colIndex): array
{
    if (!file_exists($path)) {
        throw new RuntimeException("File not found: {$path}");
    }

    $fp = fopen($path, 'r');
    if (!$fp) {
        throw new RuntimeException("Cannot open file: {$path}");
    }

    $emails = [];
    $row = 0;

    while (($cols = fgetcsv($fp)) !== false) {
        // 1行目はヘッダー
        if ($row === 0) {
            $row++;
            continue;
        }

        if (isset($cols[$colIndex])) {
            $email = strtolower(trim($cols[$colIndex]));
            if ($email !== '') {
                $emails[] = $email;
            }
        }
        $row++;
    }

    fclose($fp);
    return array_unique($emails);
}

// ===== CSV書き込み関数 =====
function saveCsv(string $path, array $emails): void
{
    $fp = fopen($path, 'w');
    if (!$fp) {
        throw new RuntimeException("Cannot write CSV: {$path}");
    }

    // ヘッダー
    fputcsv($fp, ['email']);

    foreach ($emails as $email) {
        fputcsv($fp, [$email]);
    }

    fclose($fp);
}

// ===== メイン処理 =====
$emailsA = loadCsvEmails($csvA, $colA);
$emailsB = loadCsvEmails($csvB, $colB);

// 片方にしかないメール
$onlyA = array_values(array_diff($emailsA, $emailsB));
$onlyB = array_values(array_diff($emailsB, $emailsA));

// CSV保存
saveCsv($outputA, $onlyA);
saveCsv($outputB, $onlyB);

echo "保存しました:\n";
echo " - Aにのみ存在: {$outputA} (" . count($onlyA) . "件)\n";
echo " - Bにのみ存在: {$outputB} (" . count($onlyB) . "件)\n";
