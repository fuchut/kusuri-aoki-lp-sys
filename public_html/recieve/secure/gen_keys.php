<?php
declare(strict_types=1);

$keypair = sodium_crypto_box_keypair();
$public  = sodium_crypto_box_publickey($keypair);
$secret  = sodium_crypto_box_secretkey($keypair);

@mkdir(__DIR__, 0700, true);

file_put_contents(__DIR__ . '/box_public.b64', base64_encode($public));
file_put_contents(__DIR__ . '/box_secret.b64', base64_encode($secret));
chmod(__DIR__ . '/box_secret.b64', 0600);

echo "PUBLIC (copy to A):\n" . base64_encode($public) . "\n";