<?php
$bearer = 'NTEwYWFmMjAzNTY0ZDQyZWJmNDczNWEzYjAyMDRkMzRmMGM1ZjQwNGFhNWU4ZWJhMGZkMjljNzI1MjhlNzU4Mg==';
$ch = curl_init('https://app.engn.jp/api/v1/deliveries');  //例として配信一覧取得
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $bearer,
    'Content-Type: application/json',
    'Accept-Language: ja-JP'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($code === 200) {
    echo "トークン有効です\n";
} elseif ($code === 401) {
    echo "認証エラー：トークン無効または期限切れ\n";
} else {
    echo "その他のエラー（HTTPステータス：$code）\n";
}