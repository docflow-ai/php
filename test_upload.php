<?php

require_once './docflow/APIClient.php';
require_once './vendor/autoload.php';

$client = new \DocFlow\APIClient();

if ($client->login("tester@docflow.ai", "tester2022")) {
    echo "Login {$client->isLoggedIn()}\n";


    echo "\n############################\n# all owners (projects)\n";
    foreach ($client->getOwners() as $owner) {
        echo "{$owner->name} #{$owner->id}\n";
    }


    echo "\n############################\n# current owners (projects)\n";
    $currentOwner = $client->getOwner();
    echo "{$currentOwner->name} #{$currentOwner->id} -> doctypes: (" . implode(', ', $client->getDocumentTypes()) . ")\n";


    echo "\n############################\n# change owner\n";
    $owners = $client->getOwners();
    $client->changeOwner(end($owners)->id); // last owner
    $currentOwner = $client->getOwner();
    echo "{$currentOwner->name} #{$currentOwner->id} -> doctypes: (" . implode(', ', $client->getDocumentTypes()) . ")\n";


    echo "\n############################\n# upload documents\n";
    foreach (glob(__DIR__ . '/files/*') as $filepath) {
        $id = $client->uploadDocument($filepath, 'incomingInvoice');
        echo "Upload {$filepath} as #{$id}\n";
    }

    echo "\n############################\n# logout\n";
    $client->logout();
}