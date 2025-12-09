<?php
declare(strict_types=1);

date_default_timezone_set('Asia/Tokyo');

require_once __DIR__ . '/../../public_html/re-entry/settings.php';
require_once __DIR__ . '/ReentryService.php';

require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';
require_once __DIR__ . '/PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;


function runReentryCron(ReentryService $service, bool $testMode = false): string
{
    date_default_timezone_set('Asia/Tokyo');

    $today     = date('Y-m-d');
    $yesterday = date('Y-m-d', strtotime('-1 day'));

    // --- JSON 読み込み ---
    $summaryFile = __DIR__ . "/cache/summary_{$yesterday}.json";
    $badDailyFile = __DIR__ . "/cache/bad_daily.json";

    $summary = file_exists($summaryFile)
        ? json_decode(file_get_contents($summaryFile), true)
        : null;

    $badDailyJson = file_exists($badDailyFile)
        ? (json_decode(file_get_contents($badDailyFile), true)['days'] ?? [])
        : [];

    // --- DB集計 ---
    $todayBad   = $service->getBadCountForDate($today);
    $badNight   = $service->getBadNight();
    $badDaytime = $service->getBadDaytime();

    // --- メール本文 ---
    $body  = "【自動レポート" . ($testMode ? "（テスト）" : "") . "】\n\n";

    if ($summary) {
        $body .= "【基本集計（前日まで JSON）】\n";
        $body .= "前日の不具合件数 : " . ($summary['bad_yesterday'] ?? '-') . " 件\n";
        $body .= "前日の再登録件数 : " . ($summary['reentry_yesterday'] ?? '-') . " 件\n";
        $body .= "累計再登録件数   : " . ($summary['reentry_total_until_yesterday'] ?? '-') . " 件\n";
        $body .= "未再登録者数     : " . ($summary['no_reentry_until_yesterday'] ?? '-') . " 件\n\n";
    } else {
        $body .= "前日の集計 JSON が存在しません。\n\n";
    }

    $body .= "【今日の不具合件数】\n";
    $body .= "当日分 : {$todayBad} 件\n\n";

    $body .= "【時間帯別 不具合（今日）】\n";
    $body .= "前日17時〜当日9時 : {$badNight} 件\n";
    $body .= "当日9時〜17時     : {$badDaytime} 件\n\n";


    /*======================================================
        ★★★ PHPMailer によるメール送信（確実に送る版）★★★
    ======================================================*/
    try {
        $mail = new PHPMailer(true);

        // Xserver では sendmail が最も確実
        $mail->isSendmail();

        $mail->CharSet  = 'UTF-8';
        $mail->Encoding = 'base64';
        $mail->isHTML(false);

        $fromName = '=?UTF-8?B?' . base64_encode('自動レポート') . '?=';
        $mail->setFrom(REPORT_MAIL_FROM, $fromName);

        $toList = explode(',', REPORT_MAIL_TO);
        foreach ($toList as $to) {
            $mail->addAddress(trim($to));
        }

        $mail->Subject = "再登録 自動集計レポート" . ($testMode ? "（テスト）" : "");
        $mail->Body    = $body;

        $mail->send();

    } catch (Exception $e) {
        error_log("メール送信エラー: " . $mail->ErrorInfo);
        return "メール送信エラー: " . $mail->ErrorInfo . "\n";
    }


    return $body;
}


/*======================================================
    ★★★ CLI 実行（cron 用）★★★
=======================================================*/
if (php_sapi_name() === 'cli') {

    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASSWORD,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $service = new ReentryService($pdo);

    runReentryCron($service, false);

    echo "OK\n";
}
