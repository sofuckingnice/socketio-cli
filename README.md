# socketio-cli
MIT Licenced

## install
```bash
    "require": {
        "ytake/socketio-cli": "*"
    },
```

## Send messages and Receive
```php
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

```

##  Licence

This software is distributed under MIT License. See license.txt file for more info.

## Special Thanks

Special thanks goes to Wisembly team authors of Elephant.io
