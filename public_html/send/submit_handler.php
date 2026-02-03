<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
require __DIR__ . '/sender_lib.php';

$pdo = pdoA();

// token（hiddenでPOSTされる）
$token = (string)($_POST['token'] ?? '');
try {
  assertValidToken($token);
} catch (Throwable $e) {
  http_response_code(400);
  exit('invalid token');
}

// group（hidden）
$group = strtoupper(trim((string)($_POST['group'] ?? '')));
if (!in_array($group, ['G1','G2','G3','G4'], true)) {
  http_response_code(422);
  exit('invalid group');
}

// payload（BでPII保存。Aは保存しない）
$payload = [
  'token'     => $token,
  'group'     => $group,
  'member_id' => (string)($_POST['member_id'] ?? ''),
  'email'     => (string)($_POST['email'] ?? ''),
  'name'      => (string)($_POST['name'] ?? ''),
  'tel'       => (string)($_POST['tel'] ?? ''),
  'zip'       => (string)($_POST['zip'] ?? ''),
  'address'   => (string)($_POST['address'] ?? ''),
  'present'     => (string)($_POST['present'] ?? ''),
];

// 1) Outboxへ暗号文保存（復号不能なためPII平文は残らない）
try {
  enqueueCipher($pdo, $payload);
} catch (Throwable $e) {
  // Outboxに積めない＝漏れリスクなのでfail監査して落とす（推奨）
  auditFail($pdo, $token, 'enqueue_failed: '.$e->getMessage());
  http_response_code(500);
  exit('internal error');
}

// 2) 既存のメール送信（あなたの現行処理）
// sendMail(...);

// 3) 監査：ここで「送信発生」をカウント（B到達可否は次で更新）
/**
 * 成功/失敗を厳密にするなら
 * - ここでは「試行」カウントだけ
 * - Bへの即時POST結果で success/fail を入れる
 */

// 4) 即時配送を試す（失敗してもcronが再送する）
trySendPending($pdo, 20);

// 5) 直近Outboxの送信結果は trySendPending 内でしか分からないが
//    token単位の厳密な到達成功/失敗は「即時POSTをこの場で行う」方が確実。
//    ただしOutbox方式のままでも「登録件数」を知りたい要件は満たせる（送信試行回数）
//
// ここでは「登録発生回数＝フォーム送信回数」を知りたい、が要件なので success 扱いにするのが実運用上わかりやすい。
// 到達失敗は outbox_send.log と dead管理で拾う。
auditSuccess($pdo, $token);

echo "OK";
