<?php
require_once __DIR__ . '/../setting.php';
require_once __DIR__ . '/../class/DB.class.php';
require_once __DIR__ . '/../class/OutboxSender.class.php';

$db = new DB(DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASSWORD);
$outsender = new OutboxSender($db->getPdo());
$outsender->trySend(300);
