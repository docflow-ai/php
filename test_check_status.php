<?php

use MongoDB\BSON\ObjectId;

require_once './docflow/APIClient.php';
require_once './vendor/autoload.php';

$client = new \DocFlow\APIClient();

if ($client->login("tester@docflow.ai", "tester2022")) {
    echo "Login {$client->isLoggedIn()}\n";

    $ids = [
        //"62ea44b0d239048f154c2056",
        //"62eba7b95ddf632cd132739a",
        //"62eba7b95ddf632cd132739c",
        //"62eba7ba5ddf632cd132739f",
        //"62eba7ba5ddf632cd13273a1",
        "62eba7ba5ddf632cd13273a3",
        //"62eba7bb5ddf632cd13273a5",
    ];

    foreach ($ids as $id) {
        if ($info = $client->getDocumentInfo(new ObjectId($id))) {
            //echo "{$owner->name} #{$owner->id}\n";
            print_r($info);
        } else {
            echo "Wrong doc id or you dont have rights\n";
        }
    }
}

