<?php

/*
 * A tiny TLS server used by RetryTest to reproduce dropped connections.
 *
 * Usage: php tls_server.php <pem-file> <log-file> <action>[,<action>...]
 *
 * It prints the listening port on stdout, then handles one connection per action:
 *   ok        complete the TLS handshake, read the request, answer 200
 *   500       complete the TLS handshake, read the request, answer 500
 *   drop      complete the TLS handshake, read the request, then close the TCP
 *             connection without a TLS close_notify and without answering
 *   tlsfail   close the TCP connection before the TLS handshake
 *
 * Every request read is appended to the log file as one JSON line.
 */

[, $pemFile, $logFile, $actions] = $argv;

$context = stream_context_create(['ssl' => [
    'local_cert' => $pemFile,
    'allow_self_signed' => true,
    'verify_peer' => false,
]]);
$server = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, $context);
if ($server === false) {
    fwrite(STDERR, "listen failed: $errstr\n");
    exit(1);
}
$name = stream_socket_get_name($server, false);
echo substr($name, strrpos($name, ':') + 1) . "\n";
fflush(STDOUT);

foreach (explode(',', $actions) as $action) {
    $conn = stream_socket_accept($server, 30);
    if ($conn === false) {
        exit(1);
    }

    if ($action === 'tlsfail') {
        file_put_contents($logFile, json_encode(['action' => $action]) . "\n", FILE_APPEND);
        fclose($conn);
        continue;
    }

    stream_set_blocking($conn, true);
    if (stream_socket_enable_crypto($conn, true, STREAM_CRYPTO_METHOD_TLS_SERVER) !== true) {
        fclose($conn);
        continue;
    }

    $head = '';
    while (strpos($head, "\r\n\r\n") === false && !feof($conn)) {
        $head .= fread($conn, 8192);
    }
    [$headers, $body] = explode("\r\n\r\n", $head, 2) + [1 => ''];
    $length = preg_match('/^content-length:\s*(\d+)/mi', $headers, $m) ? (int) $m[1] : 0;
    while (strlen($body) < $length && !feof($conn)) {
        $body .= fread($conn, $length - strlen($body));
    }
    [$method, $target] = explode(' ', strtok($headers, "\r\n"));
    file_put_contents($logFile, json_encode([
        'action' => $action,
        'method' => $method,
        'target' => $target,
        'body' => $body,
    ]) . "\n", FILE_APPEND);

    if ($action === 'drop') {
        // Send a TCP FIN without a TLS close_notify, as a load balancer
        // dropping the connection does. fclose() then fails to write the
        // close_notify on the shut down socket.
        stream_socket_shutdown($conn, STREAM_SHUT_RDWR);
        fclose($conn);
        continue;
    }

    $status = $action === '500' ? '500 Internal Server Error' : '200 OK';
    $payload = json_encode(['attempt_action' => $action]);
    fwrite($conn, "HTTP/1.1 $status\r\nContent-Type: application/json\r\nContent-Length: "
        . strlen($payload) . "\r\nConnection: close\r\n\r\n" . $payload);
    fclose($conn);
}
