<?php

require_once './docflow/APIClient.php';

$hash = "62f369e76bad1856b5216a0b";
$serialized = 'a:1:{s:3:"oid";s:24:"62eba7ba5ddf632cd13273a3";}';
$docflowId = new Docflow\ObjectId($hash);
$mongoId = new MongoDB\BSON\ObjectId($hash);


if ((string)$mongoId !== (string)$docflowId) {
    throw new Exception("__toString -> the result are not the same!");
}
if ($mongoId->getTimestamp() !== $docflowId->getTimestamp()) {
    throw new Exception("getTimestamp -> the result are not the same!");
}
if ($mongoId->serialize() !== $docflowId->serialize()) {
    throw new Exception("serialize -> the result are not the same!");
}
if ($mongoId->unserialize($serialized) !== $docflowId->unserialize($serialized)) {
    throw new Exception("unserialize -> the result are not the same!");
}
if ($mongoId->jsonSerialize() !== $docflowId->jsonSerialize()) {
    throw new Exception("jsonSerialize -> the result are not the same!");
}
