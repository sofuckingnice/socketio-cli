<?php
require_once "vendor/autoload.php";

$client = new \Ytake\WebSocket\Io\Client(new \Ytake\WebSocket\Io\Payload());
$client->client("http://180.235.102.58:3000")->init();