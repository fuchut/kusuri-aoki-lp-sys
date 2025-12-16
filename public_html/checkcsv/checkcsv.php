<?php

$diff = getUniqueEmails('no_reentry_20251212_102641.csv', 'BLASTMAIL_userdata_bm23569yr.csv');

print_r($diff);


function loadEmailsFromCsv(string $file, int $columnIndex): array
{
    $emails = [];

    if (($fp = fopen($file, 'r')) === false) {
        return $emails;
    }

    while (($row = fgetcsv($fp)) !== false) {
        if (!isset($row[$columnIndex])) continue;

        $email = trim($row[$columnIndex]);
        if ($email !== '') {
            // 大文字小文字統一
            $emails[] = mb_strtolower($email);
        }
    }

    fclose($fp);
    return $emails;
}

function getUniqueEmails(string $csv1, string $csv2): array
{
    // CSV1 → 1カラム目（index 0）
    $list1 = loadEmailsFromCsv($csv1, 0);

    // CSV2 → 4カラム目（index 3）
    $list2 = loadEmailsFromCsv($csv2, 3);

    // それぞれの集合（重複排除）
    $set1 = array_unique($list1);
    $set2 = array_unique($list2);

    // 片方にしかないもの
    $only1 = array_diff($set1, $set2); // csv1 にだけある
    $only2 = array_diff($set2, $set1); // csv2 にだけある

    $result = [];

    foreach ($only1 as $email) {
        $result[] = [
            'email'  => $email,
            'source' => 'csv1'
        ];
    }

    foreach ($only2 as $email) {
        $result[] = [
            'email'  => $email,
            'source' => 'csv2'
        ];
    }

    return $result;
}
