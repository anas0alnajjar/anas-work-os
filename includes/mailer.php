<?php
declare(strict_types=1);

function smtp_read_response($socket): array {
    $response = '';
    while (($line = fgets($socket, 515)) !== false) {
        $response .= $line;
        if (strlen($line) < 4 || $line[3] === ' ') break;
    }
    if ($response === '') throw new RuntimeException('SMTP server closed the connection.');
    return [(int)substr($response, 0, 3), trim($response)];
}

function smtp_expect($socket, array $expected, ?string $command = null): string {
    if ($command !== null) {
        $written = fwrite($socket, $command . "\r\n");
        if ($written === false) throw new RuntimeException('Unable to write to SMTP server.');
    }
    [$code, $response] = smtp_read_response($socket);
    if (!in_array($code, $expected, true)) {
        throw new RuntimeException('SMTP rejected the request with code ' . $code . '.');
    }
    return $response;
}

function smtp_send(array $mailConfig, string $to, string $subject, string $html, string $plain): void {
    if (empty($mailConfig['enabled'])) throw new RuntimeException('Email delivery is disabled.');

    $host = trim((string)($mailConfig['host'] ?? ''));
    $port = (int)($mailConfig['port'] ?? 465);
    $username = trim((string)($mailConfig['username'] ?? ''));
    $password = (string)($mailConfig['password'] ?? '');
    $fromEmail = trim((string)($mailConfig['from_email'] ?? $username));
    $fromName = trim((string)($mailConfig['from_name'] ?? 'Anas Work OS'));
    $timeout = max(5, min(60, (int)($mailConfig['timeout'] ?? 20)));

    foreach ([$to, $fromEmail, $username] as $email) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('Invalid mail address.');
    }
    if ($host === '' || $password === '' || $port < 1 || $port > 65535) {
        throw new InvalidArgumentException('Incomplete SMTP configuration.');
    }

    $transport = (($mailConfig['encryption'] ?? 'ssl') === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
    $context = stream_context_create(['ssl' => [
        'verify_peer' => true,
        'verify_peer_name' => true,
        'allow_self_signed' => false,
        'peer_name' => $host,
    ]]);
    $errorNumber = 0;
    $errorMessage = '';
    $socket = @stream_socket_client($transport, $errorNumber, $errorMessage, $timeout, STREAM_CLIENT_CONNECT, $context);
    if (!is_resource($socket)) throw new RuntimeException('Could not connect to SMTP server.');

    stream_set_timeout($socket, $timeout);
    try {
        smtp_expect($socket, [220]);
        $clientName = preg_replace('/[^a-z0-9.-]/i', '', gethostname() ?: 'localhost') ?: 'localhost';
        smtp_expect($socket, [250], 'EHLO ' . $clientName);
        smtp_expect($socket, [334], 'AUTH LOGIN');
        smtp_expect($socket, [334], base64_encode($username));
        smtp_expect($socket, [235], base64_encode($password));
        smtp_expect($socket, [250], 'MAIL FROM:<' . $fromEmail . '>');
        smtp_expect($socket, [250, 251], 'RCPT TO:<' . $to . '>');
        smtp_expect($socket, [354], 'DATA');

        $boundary = '=_AnasWorkOS_' . bin2hex(random_bytes(12));
        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $encodedName = '=?UTF-8?B?' . base64_encode($fromName) . '?=';
        $headers = [
            'Date: ' . date(DATE_RFC2822),
            'From: ' . $encodedName . ' <' . $fromEmail . '>',
            'To: <' . $to . '>',
            'Subject: ' . $encodedSubject,
            'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $host . '>',
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        ];
        $message = implode("\r\n", $headers) . "\r\n\r\n";
        $message .= '--' . $boundary . "\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: quoted-printable\r\n\r\n";
        $message .= quoted_printable_encode($plain) . "\r\n";
        $message .= '--' . $boundary . "\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: quoted-printable\r\n\r\n";
        $message .= quoted_printable_encode($html) . "\r\n";
        $message .= '--' . $boundary . "--\r\n";
        $message = preg_replace('/(?m)^\./', '..', $message) ?? $message;

        smtp_expect($socket, [250], $message . '.');
        smtp_expect($socket, [221], 'QUIT');
    } finally {
        fclose($socket);
    }
}
