<?php
$address = '127.0.0.1';
$port = 8080;

$sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
socket_bind($sock, $address, $port);
socket_listen($sock);

echo "Listening for WebSocket connections on ws://$address:$port...\n";

while (true) {
    $client = socket_accept($sock);
    $input = socket_read($client, 1024);
    echo "Received: $input\n";
    socket_write($client, "Hello WebSocket Client!\n");
    socket_close($client);
}
