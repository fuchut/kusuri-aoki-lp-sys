<?php
declare(strict_types=1);

/**
 * ============================================================================
 * メール送信バッチ（Web/CLI対応）
 *  - テンプレート読み込み
 *  - TEST_MODE では宛先切り替え
 *  - 出力はすべてログファイルに記録
 *  - remail_status で状態管理
 * ============================================================================
 */


/* ======================================================
   1. 設定
====================================================== */

// DB設定（適宜変更）
$db_host = "db";
$db_name = "mydatabase";
$db_user = "myuser";
$db_pass = "mypassword";
$db_port = "3306";

// テストモード（true → 宛先を変更し実送信しない）
define('MAIL_TEST_MODE', true);

// テスト時の送信先
$TEST_TO = "fuchu@works-kanazawa.com";

// メールテンプレート
$TEMPLATE_FILE = __DIR__ . "/mail_template.txt";

// 1回あたりの処理件数
$limit = 5;

// ログファイル
$LOG_DIR = __DIR__ . "/logs/";
if (!is_dir($LOG_DIR)) {
    mkdir($LOG_DIR, 0777, true);
}
$LOG_FILE = $LOG_DIR . "send_" . date("Y-m-d") . ".log";



/* ======================================================
   ログ書き込み関数
====================================================== */
function logWrite(string $msg)
{
    global $LOG_FILE;
    file_put_contents(
        $LOG_FILE,
        "[" . date('Y-m-d H:i:s') . "] " . $msg . "\n",
        FILE_APPEND
    );
}


/* ======================================================
   2. PHPMailer 読み込み
====================================================== */
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/PHPMailer/src/Exception.php';
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';



/* ======================================================
   3. DB 接続
====================================================== */
try {
    $pdo = new PDO(
        "mysql:host={$db_host};port={$db_port};dbname={$db_name};charset=utf8mb4",
        $db_user,
        $db_pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    logWrite("DB connected.");
} catch (Throwable $e) {
    logWrite("DB Connection Failed: " . $e->getMessage());
    exit;
}



/* ======================================================
   4. メールテンプレート読み込み
====================================================== */
if (!file_exists($TEMPLATE_FILE)) {
    logWrite("Template not found: {$TEMPLATE_FILE}");
    exit;
}
$template_raw = file_get_contents($TEMPLATE_FILE);
logWrite("Template loaded.");



/* ======================================================
   5. 送信対象取得
====================================================== */
$sql = "
    SELECT e.*
    FROM entry AS e
    LEFT JOIN remail_status AS s ON s.entry_id = e.id
    WHERE e.quarantine_flg = 0
      AND e.member_id NOT REGEXP '^[0-9]{16}$'
      AND (s.status IS NULL OR s.status = 'pending')
    ORDER BY e.id
    LIMIT :limit
";

$stmt = $pdo->prepare($sql);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->execute();
$list = $stmt->fetchAll(PDO::FETCH_ASSOC);

$count = count($list);
logWrite("対象件数: {$count}");

if (!$count) {
    logWrite("No pending entries.");
    exit;
}



/* ======================================================
   6. メール送信ループ
====================================================== */

foreach ($list as $row) {

    $entry_id = $row['id'];
    $email_original = $row['email'];

    // テンプレート置換
    $body = str_replace(
        ['{ENTRY_ID}', '{EMAIL}', '{NAME}'],
        [$entry_id, $email_original, 'お客様'],
        $template_raw
    );

    $subject = "キャンペーンのお知らせ";

    // TESTモードなら宛先を差し替え
    // $send_to = MAIL_TEST_MODE ? $TEST_TO : $email_original;
    $send_to = $TEST_TO;

    $ok = sendMail($send_to, $subject, $body, $entry_id, $email_original);

    // 状態更新
    if ($ok) {
        $status = 'sent';
        $sent_at = date('Y-m-d H:i:s');
        $error_message = null;
    } else {
        $status = 'error';
        $sent_at = null;
        $error_message = "Send failed";
    }

    $sql2 = "
        INSERT INTO remail_status (entry_id, status, sent_at, error_message)
        VALUES (:id, :status, :sent_at, :error_msg)
        ON DUPLICATE KEY UPDATE
            status = VALUES(status),
            sent_at = VALUES(sent_at),
            error_message = VALUES(error_message)
    ";

    $stmt2 = $pdo->prepare($sql2);
    $stmt2->execute([
        ':id'        => $entry_id,
        ':status'    => $status,
        ':sent_at'   => $sent_at,
        ':error_msg' => $error_message,
    ]);

    logWrite("remail_status updated: id={$entry_id}, status={$status}");
    usleep(3000);
}

logWrite("Batch finished.");



/* ======================================================
   7. メール送信関数（TEST/本番）
====================================================== */
function sendMail($to, $subject, $body, $entry_id = null, $original_email = null): bool
{
    // TESTモード（宛先を置き換え、送信しない）
    if (MAIL_TEST_MODE) {
        logWrite("[TEST MODE] entry_id={$entry_id}, 本来宛先={$original_email}, 実送信先={$to}");
        return true;
    }

    // 本番 SMTP 送信
    try {
        $mail = new PHPMailer(true);

        $mail->isSMTP();
        $mail->Host       = 'sv872.xbiz.ne.jp'; // ★変更必要
        $mail->SMTPAuth   = true;
        $mail->Username   = 're-entry@cp2025-kusuri-aoki.com'; // ★変更必要
        $mail->Password   = 'ATqx]#^B5qTn';        // ★変更必要
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom('re-entry@cp2025-kusuri-aoki.com', 'クスリのアオキ「40周年大感謝キャンペーン」事務局
');
        $mail->addAddress($to);

        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->CharSet = 'UTF-8';
        $mail->isHTML(false);

        $mail->send();

        logWrite("[SENT] entry_id={$entry_id}, to={$to}");
        return true;

    } catch (Exception $e) {
        logWrite("[ERROR] entry_id={$entry_id}, to={$to}, msg=" . $e->getMessage());
        return false;
    }
}
