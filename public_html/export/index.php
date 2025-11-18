<?php
declare(strict_types=1);

header('Content-Type: text/plain; charset=UTF-8');

// ---- セキュリティ（必要なら戻してください） ----
// $allowIp = 'xxx.xxx.xxx.xxx';
// if ($_SERVER['REMOTE_ADDR'] !== $allowIp) {
//     http_response_code(403);
//     exit('Forbidden');
// }

// 実行するジョブスクリプト
$script = realpath(__DIR__ . '/../../job/export/index.php');
if ($script === false) {
    echo "Job script not found.\n";
    exit(1);
}

// ★ php のパスを /usr/local/bin/php に変更
$php = '/usr/local/bin/php';

$cmd = escapeshellcmd($php) . ' ' . escapeshellarg($script) . ' 2>&1';

echo "CMD: {$cmd}\n\n";
system($cmd, $status);

echo "\nExit status: {$status}\n";
