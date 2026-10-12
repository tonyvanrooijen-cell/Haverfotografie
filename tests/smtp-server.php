<?php
// Local-only SMTP fixture: captures one message, never forwards mail.
$server = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
if (!$server) { exit(1); }
echo stream_socket_get_name($server, false) . "\n";
flush();
$client = stream_socket_accept($server, 10);
if (!$client) { exit(2); }
stream_set_timeout($client, 10);
fwrite($client, "220 localhost test\r\n");
$capture = '';
while (($line = fgets($client)) !== false) {
    $capture .= $line;
    if (str_starts_with($line, 'DATA')) {
        fwrite($client, "354 End with dot\r\n");
        while (($line = fgets($client)) !== false && $line !== ".\r\n") {
            $capture .= $line;
        }
        fwrite($client, "250 Accepted\r\n");
    } elseif (str_starts_with($line, 'QUIT')) {
        fwrite($client, "221 Bye\r\n");
        break;
    } else {
        fwrite($client, "250 OK\r\n");
    }
}
file_put_contents($argv[1], $capture);
fclose($client);
fclose($server);
