<?php
declare(strict_types=1);

class ReentryService
{
    private PDO $pdo;

    /** 不具合対象の基準日（固定値） */
    private string $fixed = '2025-12-06 00:00:00';

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /* ============================================================
        前日の不具合件数（1日分）
    ============================================================ */

    /** 指定日の不具合件数（1日分） */
    public function getBadCountForDate(string $date): int
    {
        $sql = "
            SELECT COUNT(*)
            FROM entry
            WHERE member_id NOT REGEXP '^[0-9]{16}$'
              AND DATE(updated_at) = :date
        ";
        $st = $this->pdo->prepare($sql);
        $st->execute([':date' => $date]);
        return (int)$st->fetchColumn();
    }

    /* ============================================================
        前日の再登録件数（1日分）
    ============================================================ */

    /** 指定日の再登録件数（1日分） */
    public function getReentryCountForDate(string $date): int
    {
        $sql = "
            SELECT COUNT(*)
            FROM entry AS bad
            JOIN entry AS good ON bad.email = good.email
            WHERE bad.updated_at < :fixed
              AND bad.member_id NOT REGEXP '^[0-9]{16}$'
              AND good.updated_at >= :fixed
              AND DATE(good.updated_at) = :date
              AND good.member_id REGEXP '^[0-9]{16}$'
        ";
        $st = $this->pdo->prepare($sql);
        $st->execute([
            ':fixed' => $this->fixed,
            ':date'  => $date
        ]);
        return (int)$st->fetchColumn();
    }

    /* ============================================================
        ★ 追加：前日の不具合件数（旧API互換）
    ============================================================ */
    public function getBadYesterday(): int
    {
        $y = date('Y-m-d', strtotime('-1 day'));
        return $this->getBadNightForDate($y) + $this->getBadDaytimeForDate($y);
    }

    /* ============================================================
        ★ 追加：前日の再登録件数（旧API互換）
    ============================================================ */
    public function getReentryYesterday(): int
    {
        $start = date('Y-m-d 00:00:00', strtotime('-1 day'));
        $end   = date('Y-m-d 23:59:59', strtotime('-1 day'));

        $sql = "
            SELECT COUNT(*)
            FROM entry AS bad
            JOIN entry AS good ON bad.email = good.email
            WHERE bad.updated_at < :fixed
              AND bad.member_id NOT REGEXP '^[0-9]{16}$'
              AND good.updated_at BETWEEN :start AND :end
              AND good.member_id REGEXP '^[0-9]{16}$'
        ";
        $st = $this->pdo->prepare($sql);
        $st->execute([
            ':fixed' => $this->fixed,
            ':start' => $start,
            ':end'   => $end
        ]);
        return (int)$st->fetchColumn();
    }

    /* ============================================================
        不具合集計（昼：9:30〜18:00 ／ 夜：前日18:00〜当日9:30）
    ============================================================ */

    /** 昼間（9:30〜18:00） */
    public function getBadDaytimeForDate(string $date): int
    {
        $sql = "
            SELECT COUNT(*)
            FROM entry
            WHERE member_id NOT REGEXP '^[0-9]{16}$'
              AND updated_at >= CONCAT(:date, ' 09:30:00')
              AND updated_at <  CONCAT(:date, ' 18:00:00')
        ";
        $st = $this->pdo->prepare($sql);
        $st->execute([':date' => $date]);
        return (int)$st->fetchColumn();
    }

    /** 夜間（前日18:00〜当日9:30） */
    public function getBadNightForDate(string $date): int
    {
        $sql = "
            SELECT COUNT(*)
            FROM entry
            WHERE member_id NOT REGEXP '^[0-9]{16}$'
              AND updated_at >= CONCAT(DATE_SUB(:date, INTERVAL 1 DAY), ' 18:00:00')
              AND updated_at <  CONCAT(:date, ' 09:30:00')
        ";
        $st = $this->pdo->prepare($sql);
        $st->execute([':date' => $date]);
        return (int)$st->fetchColumn();
    }

    /* ============================================================
        再登録累計（～指定時間）
    ============================================================ */
    public function getReentryTotalUntil(?string $time = null): int
    {
        if ($time === null) {
            $upper = date('Y-m-d H:i:s');
        } else {
            $upper = date('Y-m-d') . " {$time}:00";
        }

        $sql = "
            SELECT COUNT(*)
            FROM entry AS bad
            JOIN entry AS good ON bad.email = good.email
            WHERE bad.updated_at < :fixed
              AND bad.member_id NOT REGEXP '^[0-9]{16}$'
              AND good.updated_at >= :fixed
              AND good.updated_at < :upper
              AND good.member_id REGEXP '^[0-9]{16}$'
        ";
        $st = $this->pdo->prepare($sql);
        $st->execute([
            ':fixed' => $this->fixed,
            ':upper' => $upper
        ]);
        return (int)$st->fetchColumn();
    }

    /* ============================================================
        未再登録者一覧
    ============================================================ */
    public function getNoReentryList(): array
    {
        $sql = "
            SELECT bad.email, bad.member_id, bad.updated_at
            FROM entry bad
            LEFT JOIN entry good
              ON bad.email = good.email
             AND good.member_id REGEXP '^[0-9]{16}$'
             AND good.updated_at >= :fixed
            WHERE bad.member_id NOT REGEXP '^[0-9]{16}$'
              AND bad.updated_at < :fixed
              AND good.id IS NULL
            ORDER BY bad.updated_at
        ";
        $st = $this->pdo->prepare($sql);
        $st->execute([':fixed' => $this->fixed]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /* ============================================================
        ★ 追加：12/6 以降の日別不具合一覧
    ============================================================ */
    public function getBadDailySince1206(): array
    {
        $sql = "
            SELECT 
                DATE(updated_at) AS day,
                COUNT(*) AS bad_count
            FROM entry
            WHERE member_id NOT REGEXP '^[0-9]{16}$'
              AND updated_at >= '2025-12-06 00:00:00'
            GROUP BY DATE(updated_at)
            ORDER BY DATE(updated_at) ASC
        ";
        $st = $this->pdo->query($sql);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
