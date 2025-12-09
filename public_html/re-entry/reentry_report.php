<?php
declare(strict_types=1);

require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/../../job/re-entry/ReentryService.php';

date_default_timezone_set('Asia/Tokyo');

define('TOTAL_TARGET', 9848);

// -------------------------------------------------------------
// DB 接続
// -------------------------------------------------------------
$pdo = new PDO(
    "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
    DB_USER,
    DB_PASSWORD,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$service = new ReentryService($pdo);

// -------------------------------------------------------------
// パス
// -------------------------------------------------------------
$cacheDir   = __DIR__ . "/../../job/re-entry/cache/";
$today      = date('Y-m-d');
$yesterday  = date('Y-m-d', strtotime('-1 day'));


// -------------------------------------------------------------
// ■ アクション処理（最新累計 / JSON生成 / メールテスト）
// -------------------------------------------------------------
$actionResult = null;

if (!empty($_GET['action'])) {

    // ① 最新累計
    if ($_GET['action'] === 'latest_total') {
        $latestTotal  = $service->getReentryTotalUntil(null);
        $actionResult = "最新の累計再登録件数： {$latestTotal} 件";
    }

    // ② JSON生成テスト
    if ($_GET['action'] === 'json_test') {
        $script = __DIR__ . "/../../job/re-entry/daily_summary.php";

        if (file_exists($script)) {
            $cmd = "/usr/bin/php " . escapeshellarg($script) . " 2>&1";
            $actionResult = shell_exec($cmd);
        } else {
            $actionResult = "ERROR: cron_daily_summary.php が見つかりません。";
        }
    }

    // ③ テストメール送信
    if ($_GET['action'] === 'mail_test') {

        require_once __DIR__ . "/../../job/re-entry/cron_reentry.php";

        $actionResult = runReentryCron($service, true);
    }

    // ④ 初回 JSON 生成
    if ($_GET['action'] === 'init_json') {

        $script = __DIR__ . '/../../job/re-entry/init_all_json.php';

        if (file_exists($script)) {
            $cmd = "/usr/bin/php " . escapeshellarg($script) . " 2>&1";
            $actionResult = shell_exec($cmd);
        } else {
            $actionResult = "ERROR: init_all_json.php が見つかりません。";
        }
    }

}



// -------------------------------------------------------------
// ① 前日サマリー JSON
// -------------------------------------------------------------
$summaryFile = $cacheDir . "daily_summary_{$yesterday}.json";
$summary = file_exists($summaryFile)
    ? json_decode(file_get_contents($summaryFile), true)
    : null;

$badYesterday     = $summary['bad_yesterday']     ?? '-';
$reentryYesterday = $summary['reentry_yesterday'] ?? '-';


// -------------------------------------------------------------
// ② 時間帯別 不具合
// -------------------------------------------------------------
$nightFile   = $cacheDir . "bad_night.json";
$daytimeFile = $cacheDir . "bad_daytime.json";

$badNight = file_exists($nightFile)
    ? (json_decode(file_get_contents($nightFile), true)['count'] ?? null)
    : null;

$badDaytime = file_exists($daytimeFile)
    ? (json_decode(file_get_contents($daytimeFile), true)['count'] ?? null)
    : null;

if ($badNight === null)   $badNight   = $service->getBadNightForDate($today);
if ($badDaytime === null) $badDaytime = $service->getBadDaytimeForDate($today);


// -------------------------------------------------------------
// ③ 9:30 / 18:00 累計再登録
// -------------------------------------------------------------
$data930  = $cacheDir . "reentry_total_0930.json";
$data1800 = $cacheDir . "reentry_total_1800.json";

$j930  = file_exists($data930)  ? json_decode(file_get_contents($data930), true) : null;
$j1800 = file_exists($data1800) ? json_decode(file_get_contents($data1800), true) : null;

$reentry930  = $j930['count'] ?? null;
$time930     = $j930['date']  ?? null;

$reentry1800 = $j1800['count'] ?? null;
$time1800    = $j1800['date']  ?? null;


// -------------------------------------------------------------
// ④ 未再登録者数
// -------------------------------------------------------------
$noReentry930  = ($reentry930  !== null) ? (TOTAL_TARGET - $reentry930)  : "-";
$noReentry1800 = ($reentry1800 !== null) ? (TOTAL_TARGET - $reentry1800) : "-";


// -------------------------------------------------------------
// ⑤ 日別不具合（逆順）
// -------------------------------------------------------------
$badDailyFile = $cacheDir . "bad_daily.json";
$badDailyJson = file_exists($badDailyFile)
    ? (json_decode(file_get_contents($badDailyFile), true)['days'] ?? [])
    : [];

$badDailyJson = array_reverse($badDailyJson);

?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>再登録 集計レポート</title>
<style>
body {font-family:sans-serif;background:#f5f5f5;margin:0;padding:20px 0;}
.container {max-width:1200px;margin:0 auto;padding:0 20px;}

.card {background:#fff;border-radius:10px;box-shadow:0 2px 8px rgba(0,0,0,0.1);padding:20px;margin-bottom:20px;}
.card h3 {font-size:1.25rem;margin:0 0 12px;border-left:4px solid #0073aa;padding-left:8px;}

table {border-collapse:collapse;width:100%;}
th, td {border:1px solid #ddd;padding:6px;font-size:0.9rem;}

.btn {display:inline-block;padding:8px 14px;background:#0073aa;color:#fff;text-decoration:none;border-radius:6px;font-size:0.9rem;}

.columns {display:flex;gap:20px;}
.col-left {flex:1;}
.col-right {width:380px;}

.scroll-box {max-height:520px;overflow-y:auto;border:1px solid #ccc;border-radius:6px;padding:6px;background:#fafafa;}

pre {white-space:pre-wrap;}
</style>
</head>
<body>

<div class="container">

<?php if (!empty($actionResult)): ?>
<div class="card">
    <h3>実行結果</h3>
    <pre><?= htmlspecialchars((string)$actionResult) ?></pre>
</div>
<?php endif; ?>

<div class="card">
    <h3>操作メニュー</h3>
    <a class="btn" href="?action=latest_total">🔄 最新累計を取得</a>
    <a class="btn" href="?action=json_test">📄 JSON生成テスト</a>
    <a class="btn" href="?action=mail_test">📧 テストメール送信</a>
    <a class="btn" href="?action=init_json">📄 全 JSON を初回生成する</a>
</div>


<div class="columns">

<!-- 左カラム -->
<div class="col-left">

<div class="card">
    <h3>基本集計（前日分は JSON より）</h3>
    <table>
        <tr><th>項目</th><th>値</th></tr>

        <tr><td>前日の不具合件数</td>
            <td><?= htmlspecialchars((string)$badYesterday) ?></td></tr>

        <tr><td>前日の再登録件数</td>
            <td><?= htmlspecialchars((string)$reentryYesterday) ?></td></tr>

        <tr><td>累計再登録件数（9:30）</td>
            <td><?= ($reentry930 !== null)
                ? htmlspecialchars("{$reentry930} 件（{$time930} 09:30 時点）")
                : "-" ?></td></tr>
        <tr><td>未再登録者数（9:30）</td>
            <td><?= htmlspecialchars((string)$noReentry930) ?></td></tr>
        <tr><td>累計再登録件数（18:00）</td>
            <td><?= ($reentry1800 !== null)
                ? htmlspecialchars("{$reentry1800} 件（{$time1800} 18:00 時点）")
                : "-" ?></td></tr>
        <tr><td>未再登録者数（18:00）</td>
            <td><?= htmlspecialchars((string)$noReentry1800) ?></td></tr>
    </table>
</div>


<div class="card">
    <h3>時間帯別 不具合件数</h3>
    <table>
        <tr><th>時間帯</th><th>件数</th></tr>
        <tr><td>18:00〜翌09:30（夜間）</td>
            <td><?= htmlspecialchars((string)$badNight) ?></td></tr>
        <tr><td>09:30〜18:00（昼間）</td>
            <td><?= htmlspecialchars((string)$badDaytime) ?></td></tr>
    </table>
</div>


<div class="card">
    <h3>未再登録者一覧（CSV ダウンロード）</h3>
    <a class="btn" href="download_no_reentry.php">CSV をダウンロード</a>
</div>

</div><!-- /left -->


<!-- 右カラム：日別不具合 -->
<div class="col-right">
<div class="card">
    <h3>12/6 以降 日別不具合件数（逆順）</h3>
    <div class="scroll-box">
        <table>
            <tr><th>日付</th><th>件数</th></tr>
            <?php foreach ($badDailyJson as $row): ?>
            <tr>
                <td><?= htmlspecialchars((string)$row['day']) ?></td>
                <td><?= htmlspecialchars((string)$row['bad_count']) ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
</div>
</div><!-- /right -->

</div><!-- /columns -->

</div><!-- /container -->
</body>
</html>
