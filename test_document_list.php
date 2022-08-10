<?php

require_once './docflow/APIClient.php';
require_once './vendor/autoload.php';

date_default_timezone_set('Europe/Bratislava');
$client = new \DocFlow\APIClient();

if ($client->login("tester@docflow.ai", "tester2022")) {
    echo "Login {$client->isLoggedIn()}\n";
    $owner = $client->getOwner();

    echo "{$owner->name} #{$owner->id}\n";

    $from = new DateTime('-1 month');
    $to = new DateTime;

    echo "\n\nDocuments list (all) from {$from->format('d.m.Y')} to {$to->format('d.m.Y')}\n\n";
    foreach ($client->getDocumentList($from, $to) as $docId) {
        if ($info = $client->getDocumentInfo($docId)) {
            echo "{$info->name} (#{$docId} / {$info->doctype}) step: {$info->step}, created: {$info->createdAt}\n";
        }
    }

    echo "\n\nDocuments list (receipt) from {$from->format('d.m.Y')} to {$to->format('d.m.Y')}\n\n";
    foreach ($client->getDocumentList($from, $to, 'receipt') as $docId) {
        if ($info = $client->getDocumentInfo($docId)) {
            echo "{$info->name} (#{$docId} / {$info->doctype}) step: {$info->step}, created: {$info->createdAt}\n";
        }
    }

    echo "\n\nDocuments list (step >= 2 ... verified) from {$from->format('d.m.Y')} to {$to->format('d.m.Y')}\n\n";
    foreach ($client->getDocumentList($from, $to, null, [-1,2,3,4,5,6,7,8]) as $docId) {
        if ($info = $client->getDocumentInfo($docId)) {
            echo "{$info->name} (#{$docId} / {$info->doctype}) step: {$info->step}, created: {$info->createdAt}\n";
        }
    }
}

