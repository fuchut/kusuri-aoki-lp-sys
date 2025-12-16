<?php
declare(strict_types=1);

header('Content-Type: text/plain; charset=UTF-8');

// ----------------------------------------------------
// セキュリティ（IP制限）
// ----------------------------------------------------
$allowIp = '202.122.50.50';   // 許可するグローバル IP に変更
if ($_SERVER['REMOTE_ADDR'] !== $allowIp) {
    http_response_code(403);
    exit("Forbidden: " . $_SERVER['REMOTE_ADDR']);
}

// ----------------------------------------------------
// 実行するジョブ側のスクリプト
// ----------------------------------------------------
$script = realpath(__DIR__ . '/../../job/export/rebuild_exports.php');
if ($script === false || !file_exists($script)) {
    echo "Job script not found.\n";
    exit(1);
}

// ----------------------------------------------------
// CLI の PHP コマンドパス（Xserver の場合）
// ----------------------------------------------------
$php = '/usr/bin/php8.3';   // 環境に合わせて変更

// ----------------------------------------------------
// 実行コマンドを生成
// ----------------------------------------------------
$cmd = escapeshellcmd($php) . ' ' . escapeshellarg($script) . ' 2>&1';

echo "CMD: {$cmd}\n\n";

// ----------------------------------------------------
// 実行
// ----------------------------------------------------
system($cmd, $status);

echo "\nExit status: {$status}\n";
