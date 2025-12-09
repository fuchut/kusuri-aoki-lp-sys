<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../bin/vendor/autoload.php';      

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

define('DB_NAME', 'xs632051_aokicheck');
define('DB_USER', 'xs632051_works');
define('DB_PASSWORD', 'works24104');
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');

// ===== DB接続 =====
$dsn = sprintf(
    'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
    DB_HOST,
    DB_PORT,
    DB_NAME
);

try {
    $pdo = new PDO($dsn, DB_USER, DB_PASSWORD, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (PDOException $e) {
    die("DB Connection Error: " . $e->getMessage());
}

// ===== 出力先 =====
$exportDir = __DIR__ . '/invalid_member_id_xlsx';
if (!is_dir($exportDir)) {
    mkdir($exportDir, 0775, true);
}

$filename = $exportDir . '/invalid_member_id_' . date('Ymd_His') . '.xlsx';

// ===== SQL：16桁の数字以外 =====
$sql = "
    SELECT 
        member_id,
        present,
        email,
        updated_at
    FROM entry
    WHERE member_id NOT REGEXP '^[0-9]{16}$'
    ORDER BY updated_at ASC
";

$stmt = $pdo->query($sql);

// ===== Excelブック作成 =====
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// ヘッダー
$headers = ['member_id', 'present', 'email', 'updated_at'];
$col = 'A';
foreach ($headers as $header) {
    $sheet->setCellValue($col . '1', $header);
    $col++;
}

// ===== データ書き込み =====
$rowNum = 2;
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {

    // Excelで先頭0を維持するため文字列として設定
    $sheet->setCellValueExplicit(
        'A' . $rowNum,
        $row['member_id'],
        \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING
    );

    $sheet->setCellValue('B' . $rowNum, $row['present']);
    $sheet->setCellValue('C' . $rowNum, $row['email']);
    $sheet->setCellValue('D' . $rowNum, $row['updated_at']);

    // 日付セルを Excel 日付形式にする（表示書式設定）
    $sheet->getStyle('D' . $rowNum)
        ->getNumberFormat()
        ->setFormatCode(NumberFormat::FORMAT_DATE_DATETIME);

    $rowNum++;
}

// ===== 自動カラム幅 =====
foreach (range('A', 'D') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// ===== 保存 =====
$writer = new Xlsx($spreadsheet);

try {
    $writer->save($filename);
    echo "Excel export complete: $filename\n";
} catch (Throwable $e) {
    die("Excel保存エラー: " . $e->getMessage());
}
