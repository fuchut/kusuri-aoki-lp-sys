<?php
declare(strict_types=1);

/**
 * relation-brastengine/index.php（CSP対応・簡易結果表示・UI整形）
 * - 実行結果は簡易通知のみ（詳細診断/ジョブ出力は表示しない）
 * - ログ表示：左 span-8、ログクリア：右 span-4（横並び）
 * - 「隔離解除」「送信済み解除」横並び + ID指定はデフォ非表示（:has() + JSフォールバック）
 * - 外部 app.js 前提（同階層）
 * - br_tx_job.php は Web 実行時 return する実装（exitしない）にしておくこと
 */

session_start();

/* ---- パス（Xserverミラー構成） ---- */
$JOB_SCRIPT = dirname(__DIR__) . '/../job/relation-brastengine/br_tx_job.php';
$WORKDIR    = dirname(__DIR__) . '/../job/relation-brastengine/tx_job_work';
$LOG_PATH   = $WORKDIR . '/job.log';

require_once __DIR__ . '/../formapp/setting.php';
require_once __DIR__ . '/../formapp/class/DB.class.php';

/* ---- CSRF ---- */
if (empty($_SESSION['csrf'])) {
  $_SESSION['csrf'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['csrf'];

/* ---- DB ---- */
$errs = [];
$info = [];
try {
  $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);
  $pdo = new PDO($dsn, DB_USER, DB_PASSWORD, [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);
} catch (Throwable $e) {
  $errs[] = 'DB接続エラー: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
  $pdo = null;
}

/* ---- Action ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!hash_equals($csrf, $_POST['csrf'] ?? '')) {
    $errs[] = '不正なリクエスト（CSRF）。';
  } else {
    $action = $_POST['action'] ?? '';
    try {
      switch ($action) {
        case 'run_now': {
          // ジョブ本体を実行（出力はバッファリングして捨てる）
          if (!is_file($JOB_SCRIPT)) {
            throw new RuntimeException("ジョブ本体が見つかりません: {$JOB_SCRIPT}");
          }
          define('ALLOW_WEB_RUN', true);
          $cwdBak = getcwd();
          @chdir(dirname($JOB_SCRIPT));
          ob_start();
          try { require $JOB_SCRIPT; } catch (Throwable $e) { /* 標準出力はUIに出さない */ }
          ob_end_clean();
          if ($cwdBak) @chdir($cwdBak);


          // --- 送信件数の取得（job.log の最終 "sent=" を読む） ---
          $sentCount = null;

          if (is_file($LOG_PATH)) {
              $log = @file($LOG_PATH, FILE_IGNORE_NEW_LINES);
              if (is_array($log)) {
                  // 末尾50行から検索
                  foreach (array_reverse(array_slice($log, -50)) as $line) {
                      if (preg_match('/sent\s*=\s*(\d+)/', $line, $m)) {
                          $sentCount = (int)$m[1];
                          break;
                      }
                  }
              }
          }

          $sentMsg = ($sentCount !== null)
              ? "送信しました：{$sentCount}件"
              : "送信しました（件数不明）";

          // ✅ 通知カードのリストに1件追加する（送信済み解除と同じ形式）
          $info[] = htmlspecialchars($sentMsg, ENT_QUOTES, 'UTF-8');

          break;
        }

        case 'unquarantine': {
          if (!$pdo) throw new RuntimeException('DB接続なし');
          $target = $_POST['target'] ?? 'all';
          if ($target === 'all') {
            $cnt = $pdo->exec("UPDATE entry SET quarantine_flg=0 WHERE quarantine_flg=1");
            $info[] = "隔離解除（全件）: {$cnt} 件";
          } else {
            $raw = (string)($_POST['ids'] ?? '');
            $ids = array_values(array_filter(array_map('trim', preg_split('/[,\s]+/', $raw))));
            $ids = array_map('intval', array_filter($ids, fn($v)=>ctype_digit((string)$v)));
            if (!$ids) throw new InvalidArgumentException('IDが正しく指定されていません。');
            $in = implode(',', array_fill(0, count($ids), '?'));
            $st = $pdo->prepare("UPDATE entry SET quarantine_flg=0 WHERE id IN ($in)");
            $st->execute($ids);
            $info[] = '隔離解除（ID指定）: ' . htmlspecialchars(implode(',', $ids), ENT_QUOTES, 'UTF-8');
          }
          break;
        }

        case 'unsent_clear': {
          if (!$pdo) throw new RuntimeException('DB接続なし');
          $target = $_POST['target'] ?? 'all';
          if ($target === 'all') {
            $cnt = $pdo->exec("UPDATE entry SET send_flg=0 WHERE send_flg=1");
            $info[] = "送信済み解除（全件）: {$cnt} 件";
          } else {
            $raw = (string)($_POST['ids'] ?? '');
            $ids = array_values(array_filter(array_map('trim', preg_split('/[,\s]+/', $raw))));
            $ids = array_map('intval', array_filter($ids, fn($v)=>ctype_digit((string)$v)));
            if (!$ids) throw new InvalidArgumentException('IDが正しく指定されていません。');
            $in = implode(',', array_fill(0, count($ids), '?'));
            $st = $pdo->prepare("UPDATE entry SET send_flg=0 WHERE id IN ($in)");
            $st->execute($ids);
            $info[] = '送信済み解除（ID指定）: ' . htmlspecialchars(implode(',', $ids), ENT_QUOTES, 'UTF-8');
          }
          break;
        }

        case 'log_clear': {
          $backup = $_POST['backup'] ?? 'none'; // 既定: 保存しない
          if (!is_file($LOG_PATH)) {
            $info[] = 'ログはまだありません。';
          } else {
            if ($backup === 'save') {
              $ts  = date('Ymd_His');
              $dst = $WORKDIR . "/job.log.{$ts}.bak";
              if (!@copy($LOG_PATH, $dst)) throw new RuntimeException('バックアップの作成に失敗しました。');
              $info[] = 'バックアップ保存: ' . htmlspecialchars(basename($dst), ENT_QUOTES, 'UTF-8');
            }
            $fp = fopen($LOG_PATH, 'c+');
            if ($fp) { ftruncate($fp, 0); fclose($fp); }
            $info[] = 'ログをクリアしました。';
          }
          break;
        }
      }
    } catch (Throwable $e) {
      $errs[] = htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
    }
  }
}

/* ---- Log ---- */
$logText = (is_file($LOG_PATH) && ($raw = @file_get_contents($LOG_PATH)) !== false) ? $raw : '';
?>
<!doctype html>
<html lang="ja">
<head>
<meta charset="utf-8">
<title>Blastengine トランザクション管理UI</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
:root{
  --bg:#f7f7fb; --card:#fff; --bd:#e5e7eb; --fg:#111; --muted:#6b7280;
  --primary:#111; --primary-fg:#fff; --ok:#0a7; --ng:#d43;
  --radius:12px; --pad:16px; --gap:16px; --shadow:0 4px 16px rgba(0,0,0,.06);
}
*{box-sizing:border-box}
html,body{height:100%}
body{margin:0;color:var(--fg);background:var(--bg);font-family:system-ui,-apple-system,"Segoe UI",Roboto,"Hiragino Sans","Noto Sans JP",sans-serif;}
header{padding:20px var(--pad);border-bottom:1px solid var(--bd);background:#fff;position:sticky;top:0;z-index:10}
h1{margin:0;font-size:20px}
main{padding:20px;max-width:1200px;margin:0 auto}
.card{background:var(--card);border:1px solid var(--bd);border-radius:var(--radius);padding:var(--pad);box-shadow:var(--shadow)}
h2{font-size:16px;margin:0 0 10px}
.row{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin:10px 0}
.grid-2{display:grid;grid-template-columns:1fr 1fr;gap:var(--gap);align-items:start}
.grid-log{display:grid;grid-template-columns:2fr 1fr;gap:var(--gap);align-items:start} /* span-8 / span-4 */
input[type="text"]{border:1px solid var(--bd);border-radius:10px;padding:.55rem .8rem;min-width:260px;background:#fff}
.radio-group label{display:inline-flex;align-items:center;gap:.5rem;padding:.35rem .6rem;border:1px solid var(--bd);border-radius:999px;background:#fff}
button{background:var(--primary);color:var(--primary-fg);border:0;border-radius:10px;padding:.6rem 1rem;cursor:pointer}
button.secondary{background:#f3f4f6;color:#111;border:1px solid var(--bd)}
.small{font-size:12px;color:var(--muted)}
pre.log{background:#0b1020;color:#e4f1ff;border-radius:10px;padding:12px;max-height:60vh;overflow:auto;border:1px solid #0e1835}
.msg-ok{color:var(--ok)} .msg-ng{color:var(--ng)}
/* ID欄：初期は非表示。:has() で表示切替（CSP非依存） */
.id-input-row{display:none;gap:.5rem;align-items:center}
form[data-toggle="id-target"]:has(input[name='target'][value='id']:checked) .id-input-row{display:flex}
/* 実行結果カードのヘッダー */
.result-card .result-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:8px}
.result-card .result-actions{display:flex;gap:8px}
</style>
</head>
<body>
<header><h1>Blastengine トランザクション管理UI</h1></header>
<main>

<?php if ($errs): ?>
  <div class="card" style="border-color:#f4d1d1;background:#fff7f7">
    <h2>エラー</h2>
    <ul style="margin:8px 0 0 16px">
      <?php foreach ($errs as $e): ?><li class="msg-ng"><?= $e ?></li><?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<?php if ($info): ?>
  <div class="card" style="border-color:#cdeee4;background:#f6fffb">
    <h2>通知</h2>
    <ul style="margin:8px 0 0 16px">
      <?php foreach ($info as $m): ?><li class="msg-ok"><?= $m ?></li><?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<!-- 上段：今すぐ実行 / 補助ツール -->
<div class="grid-2" style="margin-bottom:var(--gap);">
  <div class="card">
    <h2>今すぐ実行</h2>
    <form method="post" class="js-confirm" data-confirm="ジョブを実行します。よろしいですか？">
      <input type="hidden" name="csrf" value="<?= $csrf ?>">
      <input type="hidden" name="action" value="run_now">
      <div class="row">
        <button type="submit">ジョブ実行</button>
        <span class="small">本体: <code><?= htmlspecialchars($JOB_SCRIPT, ENT_QUOTES, 'UTF-8') ?></code></span>
      </div>
    </form>
  </div>

  <div class="card">
    <h2>補助ツール</h2>
    <p class="small" style="margin:0">（必要に応じて機能追加スペース）</p>
  </div>
</div>

<!-- 中段：隔離解除・送信済み解除（横並び） -->
<div class="grid-2" style="margin:var(--gap) 0;">
  <div class="card">
    <h2>隔離解除</h2>
    <form method="post" data-toggle="id-target" class="js-confirm" data-confirm="隔離解除を実行します。よろしいですか？">
      <input type="hidden" name="csrf" value="<?= $csrf ?>">
      <input type="hidden" name="action" value="unquarantine">

      <div class="row radio-group">
        <label><input type="radio" name="target" value="all" checked> 全件</label>
        <label><input type="radio" name="target" value="id"> ID指定</label>
      </div>

      <div class="row id-input-row">
        <label for="ids_unq" class="small">ID</label>
        <input id="ids_unq" type="text" name="ids" placeholder="例: 12,15,21" inputmode="numeric" pattern="[0-9, ]*">
        <span class="small">カンマ区切りで複数指定</span>
      </div>

      <div class="row">
        <button type="submit">実行</button>
      </div>
    </form>
  </div>

  <div class="card">
    <h2>送信済み解除</h2>
    <form method="post" data-toggle="id-target" class="js-confirm" data-confirm="送信済み解除を実行します。よろしいですか？">
      <input type="hidden" name="csrf" value="<?= $csrf ?>">
      <input type="hidden" name="action" value="unsent_clear">

      <div class="row radio-group">
        <label><input type="radio" name="target" value="all" checked> 全件</label>
        <label><input type="radio" name="target" value="id"> ID指定</label>
      </div>

      <div class="row id-input-row">
        <label for="ids_unsent" class="small">ID</label>
        <input id="ids_unsent" type="text" name="ids" placeholder="例: 8,9,10" inputmode="numeric" pattern="[0-9, ]*">
        <span class="small">カンマ区切りで複数指定</span>
      </div>

      <div class="row">
        <button type="submit">実行</button>
      </div>
    </form>
  </div>
</div>

<!-- 下段：ログ表示（左 span-8）＋ ログクリア（右 span-4） -->
<div class="grid-log">
  <div class="card" id="logs">
    <h2>ログ表示</h2>
    <?php if ($logText===''): ?>
      <p class="small">ログはまだありません。</p>
    <?php else: ?>
      <pre class="log"><?= htmlspecialchars($logText, ENT_QUOTES, 'UTF-8') ?></pre>
    <?php endif; ?>
  </div>

  <div class="card">
    <h2>ログクリア</h2>
    <form method="post" class="js-confirm" data-confirm="ログをクリアします。よろしいですか？">
      <input type="hidden" name="csrf" value="<?= $csrf ?>">
      <input type="hidden" name="action" value="log_clear">

      <div class="row radio-group">
        <label><input type="radio" name="backup" value="none" checked> 保存しない（既定）</label>
        <label><input type="radio" name="backup" value="save"> バックアップを保存</label>
      </div>

      <div class="row">
        <button type="submit">ログをクリア</button>
        <button type="button" class="secondary js-reload">再読込</button>
      </div>

      <p class="small" style="margin:10px 0 0">ファイル：<code><?= htmlspecialchars($LOG_PATH, ENT_QUOTES, 'UTF-8') ?></code></p>
    </form>
  </div>
</div>

</main>

<!-- 外部JS（CSP準拠） -->
<script src="./app.js" defer></script>
</body>
</html>
