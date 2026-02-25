<?php
declare(strict_types=1);

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

final class XlsxExporter
{
    private PDO $pdo;
    private string $dir;

    public function __construct(PDO $pdo, string $dir)
    {
        $this->pdo = $pdo;
        $this->dir = rtrim($dir, '/');
        $this->ensureDir();
    }

    private function ensureDir(): void
    {
        if (!is_dir($this->dir)) {
            if (!mkdir($this->dir, 0755, true)) {
                throw new RuntimeException('XLSX_DIR mkdir failed: ' . $this->dir);
            }
        }
        if (!is_writable($this->dir)) {
            throw new RuntimeException('XLSX_DIR not writable: ' . $this->dir);
        }
    }

    public function exportAllGroups(): array
    {
        $groups = $this->pdo->query("SELECT DISTINCT group_name FROM entries ORDER BY group_name ASC")->fetchAll();
        $out = [
            'ok' => true,
            'dir' => $this->dir,
            'generated' => [],
        ];

        foreach ($groups as $g) {
            $name = (string)$g['group_name'];
            $out['generated'][] = $this->exportOneGroup($name);
        }
        return $out;
    }

    public function exportOneGroup(string $group): array
    {
        $group = trim($group);
        if ($group === '' || strlen($group) > 255) {
            throw new InvalidArgumentException('invalid group');
        }

        $st = $this->pdo->prepare("
            SELECT
              token,
              group_name,
              member_id,
              email,
              last_name,
              first_name,
              tel,
              zip,
              address,
              present,
              created_at,
              updated_at
            FROM entries
            WHERE group_name = :g
            ORDER BY updated_at DESC
        ");
        $st->execute([':g' => $group]);
        $rows = $st->fetchAll();

        $safe = preg_replace('/[^A-Za-z0-9._-]+/', '_', $group) ?: 'group';
        $path = $this->dir . '/entries_' . $safe . '.xlsx';

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // シート名（31文字制限 + 禁止文字）
        $sheetName = preg_replace('/[\[\]\*\:\?\/\\\\]/u', '_', $group) ?: 'group';
        $sheetName = mb_substr($sheetName, 0, 31);
        $sheet->setTitle($sheetName);

        $header = ['token','group_name','member_id','email','last_name','first_name','tel','zip','address','present','created_at','updated_at'];
        $sheet->fromArray($header, null, 'A1');

        // ★ 列ごとに文字列固定（A〜Lを文字列扱い）
        $sheet->getStyle('A:L')
              ->getNumberFormat()
              ->setFormatCode('@');

        $rowNo = 2; // データ開始行
        foreach ($rows as $r) {
            $sheet->setCellValueExplicit('A'.$rowNo, (string)($r['token'] ?? ''), DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('B'.$rowNo, (string)($r['group_name'] ?? ''), DataType::TYPE_STRING);

            // ★ E+15防止（必須）
            $sheet->setCellValueExplicit('C'.$rowNo, (string)($r['member_id'] ?? ''), DataType::TYPE_STRING);

            $sheet->setCellValueExplicit('D'.$rowNo, (string)($r['email'] ?? ''), DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('E'.$rowNo, (string)($r['last_name'] ?? ''), DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('F'.$rowNo, (string)($r['first_name'] ?? ''), DataType::TYPE_STRING);

            // ★ 先頭0防止（必須）
            $sheet->setCellValueExplicit('G'.$rowNo, (string)($r['tel'] ?? ''), DataType::TYPE_STRING);

            // ★ 郵便番号も先頭0が消えるので文字列（推奨）
            $sheet->setCellValueExplicit('H'.$rowNo, (string)($r['zip'] ?? ''), DataType::TYPE_STRING);

            $sheet->setCellValueExplicit('I'.$rowNo, (string)($r['address'] ?? ''), DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('J'.$rowNo, (string)($r['present'] ?? ''), DataType::TYPE_STRING);

            // 日付は文字列で出すならこれ（そのままの表示になる）
            $sheet->setCellValueExplicit('K'.$rowNo, (string)($r['created_at'] ?? ''), DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('L'.$rowNo, (string)($r['updated_at'] ?? ''), DataType::TYPE_STRING);

            $rowNo++;
        }

        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:L1');

        foreach (range('A', 'L') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // 上書き保存
        $writer = new Xlsx($spreadsheet);
        $writer->save($path);

        return [
            'ok' => true,
            'group' => $group,
            'path' => $path,
            'count' => count($rows),
        ];
    }
}
