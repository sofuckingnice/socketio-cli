<?php
require(__DIR__ . "/../vendor/autoload.php");

use Ytake\WebSocket\Io;

// instance
$client = new Io\Client(new Io\Payload, new Io\Header, new Io\Log);

// simple use
$client->client("http://localhost:3000")->connection()->disconnect();

// namespace support
$client->client("http://localhost:3000")->query(['query' => 1])
    // namespace
    ->of('/active')->connection(function() use($client){
            // event receive
            $client->on('connection', function($data) use($client){
                    // value from socket.io server
                    var_dump($data);
                });
            // event emit
            $client->emit('sender', ['hello']);
            // event receive
            $client->on('message', function($data) use($client){
                    // value from socket.io server
                    var_dump($data);
                    $client->disconnect();
                });
        })->keepAlive();