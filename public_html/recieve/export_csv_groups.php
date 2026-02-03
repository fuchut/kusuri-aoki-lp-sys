<?php
declare(strict_types=1);

require_once __DIR__ . '/setting.php';

$isCli = (PHP_SAPI === 'cli');

// ブラウザ実行はキー必須
if (!$isCli) {
  $key = (string)($_GET['key'] ?? '');
  if ($key === '' || !hash_equals((string)CSV_RUN_KEY, $key)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    exit("Forbidden\n");
  }

  // ブラウザからのキャッシュ抑止
  header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
  header('Pragma: no-cache');
}

$CSV_DIR = __DIR__ . '/csv/data_csv';
if (!is_dir($CSV_DIR)) {
  mkdir($CSV_DIR, 0755, true);
}

$dsn = 'mysql:host='.(string)DB_HOST.';port='.(string)DB_PORT.';dbname='.(string)DB_NAME.';charset=utf8mb4';
$pdo = new PDO($dsn, (string)DB_USER, (string)DB_PASSWORD, [
  PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$groups = $pdo->query("SELECT DISTINCT group_name FROM entries ORDER BY group_name ASC")->fetchAll();

$header = ['token','group_name','member_id','email','last_name','first_name','tel','zip','address','present','created_at','updated_at'];

foreach ($groups as $gr) {
  $g = (string)$gr['group_name'];
  $safe = preg_replace('/[^A-Za-z0-9._-]+/', '_', $g);
  if ($safe === '' || $safe === null) $safe = 'group';

  $path = $CSV_DIR . '/entries_' . $safe . '.csv';

  $fp = fopen($path, 'wb');
  if ($fp === false) {
    throw new RuntimeException('cannot open csv: ' . $path);
  }

  // ★ 排他ロック（多重起動防止）
  if (!flock($fp, LOCK_EX)) {
    fclose($fp);
    throw new RuntimeException('cannot lock csv: ' . $path);
  }

  try {
    // ヘッダ行
    $line = implode(',', array_map('csv_cell', $header)) . "\r\n";
    fwrite($fp, $line);
    // fwrite($fp, mb_convert_encoding($line, 'SJIS-win', 'UTF-8')); SJIS変換版

    $st = $pdo->prepare("
      SELECT token, group_name, member_id, email, last_name, first_name, tel, zip, address, present, created_at, updated_at
      FROM entries
      WHERE group_name = :g
      ORDER BY updated_at DESC
    ");
    $st->execute([':g' => $g]);

    // 1行ずつ書き出し
    while ($r = $st->fetch()) {
      $row = [
        (string)$r['token'],
        (string)$r['group_name'],
        (string)($r['member_id'] ?? ''),
        (string)($r['email'] ?? ''),
        (string)($r['last_name'] ?? ''),
        (string)($r['first_name'] ?? ''),
        (string)($r['tel'] ?? ''),
        (string)($r['zip'] ?? ''),
        (string)($r['address'] ?? ''),
        (string)($r['present'] ?? ''),
        (string)$r['created_at'],
        (string)$r['updated_at'],
      ];
      $line = implode(',', array_map('csv_cell', $row)) . "\r\n";
      fwrite($fp, $line);
    }
  } finally {
    // ★ ロック解除 → クローズ
    flock($fp, LOCK_UN);
    fclose($fp);
  }
}

echo "OK\n";
if (PHP_SAPI !== 'cli') {
  echo "done at: " . date('Y-m-d H:i:s') . "\n";
}

function csv_cell(string $s): string {
  $s = str_replace(["\r\n","\r","\n"], ' ', $s);
  $needs = (strpbrk($s, "\",\n\r") !== false);
  $s = str_replace('"', '""', $s);
  return $needs ? '"'.$s.'"' : $s;
}
