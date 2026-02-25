<?php
declare(strict_types=1);

require_once __DIR__ . '/../setting.php';

$key = (string)($_GET['key'] ?? '');
if ($key === '' || !hash_equals((string)XLSX_RUN_KEY, $key)) {
  http_response_code(403);
  echo 'Forbidden';
  exit;
}

$autoload = (string)COMPOSER_AUTOLOAD;
if (!is_file($autoload)) {
  http_response_code(500);
  echo 'Composer autoload not found';
  exit;
}
require_once $autoload;

require_once __DIR__ . '//XlsxExporter.class.php';

$dsn = 'mysql:host='.(string)DB_HOST.';port='.(string)DB_PORT.';dbname='.(string)DB_NAME.';charset=utf8mb4';
$pdo = new PDO($dsn, (string)DB_USER, (string)DB_PASSWORD, [
  PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$groups = $pdo->query("SELECT DISTINCT group_name FROM entries ORDER BY group_name ASC")->fetchAll();

$exporter = new XlsxExporter($pdo, (string)XLSX_DIR);

$result = null;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  $target = (string)($_POST['group'] ?? '');
  if ($target === '') {
    $result = $exporter->exportAllGroups();
  } else {
    $result = $exporter->exportOneGroup($target);
  }
}

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

?>
<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <title>XLSX 出力コンパネ</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body{font-family:system-ui,-apple-system,"Segoe UI",sans-serif;padding:16px;}
    .box{max-width:900px;}
    select,button{padding:10px;font-size:14px;}
    pre{background:#111;color:#0f0;padding:12px;overflow:auto;}
  </style>
</head>
<body>
<div class="box">
  <h1>XLSX 出力（group_name別）</h1>
  <p>出力先： <code><?= h((string)XLSX_DIR) ?></code></p>

  <form method="post">
    <label>対象group：</label>
    <select name="group">
      <option value="">（全group）</option>
      <?php foreach ($groups as $g): ?>
        <?php $gn = (string)$g['group_name']; ?>
        <option value="<?= h($gn) ?>"><?= h($gn) ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit">XLSX生成</button>
  </form>

  <?php if ($result !== null): ?>
    <h2>結果</h2>
    <pre><?= h(json_encode($result, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)) ?></pre>
  <?php endif; ?>
</div>
</body>
</html>
