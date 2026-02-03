<?php
/****************************************
 * CronからのOutbox送信試行
 ***************************************/

declare(strict_types=1);

require __DIR__ . '/config.php';
require __DIR__ . '/sender_lib.php';

$pdo = pdoA();
trySendPending($pdo, 300);
echo "OK\n";
