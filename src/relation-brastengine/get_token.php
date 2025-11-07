<?php
$loginId = 'be76036en';
$apiKey  = 'GgihKKwhxcd6l81FlEZf5exy0e59CK0f0ExtAfEAeCytsiRnQLGvghQrjAff9HKj';

// 1. ログインIDとAPIキーを連結してSHA256でハッシュ化
$hash = hash('sha256', $loginId . $apiKey);

// 2. アルファベットを小文字に（hash()の結果はすでに小文字なので省略可能）
$lower = strtolower($hash);

// 3. base64エンコード
$bearerToken = base64_encode($lower);

// 4. 出力
echo $bearerToken;