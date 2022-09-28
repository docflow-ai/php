<?php

require_once './docflow/APIClient.php';
require_once './vendor/autoload.php';

$client = new \DocFlow\APIClient();

if ($client->login("tester@docflow.ai", "tester2022")) {
    echo "Login {$client->isLoggedIn()}\n";

    $ids = [
        new DocFlow\ObjectId("62f369e76bad1856b5216a0b"),
        new DocFlow\ObjectId("62f2170f16f0fb32720b3a89"),
        new DocFlow\ObjectId("62eba7ba5ddf632cd13273a3"),
        new DocFlow\ObjectId("6322f4954820414f2171469f")
    ];
    $multi = true;

    if (($data = $client->export($client::EXPORT_MKSOFT, $ids, $multi))) {
        foreach ($data as $filename => $content) {
            // show content of file
            echo $filename . "\n";
            echo substr($content, 0, 50) . "...\n";
        }
    } else {
        echo "Wrong doc id or you dont have rights\n";
    }
}
