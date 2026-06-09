<?php
/**
 * smtp_send.php — rojasmardones.com
 * Minimal SMTP-over-SSL mailer. No external libraries.
 * Requires smtp_config.php to be loaded first.
 *
 * smtp_mail(string $to, string $to_name, string $subject,
 *           string $plain, string $html, string $reply_to = ''): bool
 */

function smtp_mail(
    string $to,
    string $to_name,
    string $subject,
    string $plain,
    string $html,
    string $reply_to = ''
): bool {

    $host      = SMTP_HOST;
    $port      = SMTP_PORT;
    $user      = SMTP_USER;
    $pass      = SMTP_PASS;
    $from      = SMTP_FROM;
    $from_name = SMTP_FROM_NAME;

    $log = static fn(string $s) => error_log("[smtp_send] {$s}");

    // ── Build MIME message ────────────────────────────────────────
    $boundary = 'RM_' . md5(uniqid('', true));

    $mime_headers  = "MIME-Version: 1.0\r\n";
    $mime_headers .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"";
    if ($reply_to) {
        $mime_headers .= "\r\nReply-To: {$reply_to}";
    }

    $body  = "--{$boundary}\r\n";
    $body .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
    $body .= $plain . "\r\n\r\n";
    $body .= "--{$boundary}\r\n";
    $body .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
    $body .= $html . "\r\n\r\n";
    $body .= "--{$boundary}--";

    // Full DATA payload (RFC 2822)
    $enc_subject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $data  = "From: {$from_name} <{$from}>\r\n";
    $data .= "To: {$to_name} <{$to}>\r\n";
    $data .= "Subject: {$enc_subject}\r\n";
    $data .= $mime_headers . "\r\n\r\n";
    $data .= $body;

    // Escape SMTP data-transparency dots
    $data = str_replace("\n.", "\n..", $data);

    // ── Connect (SSL port 465) ────────────────────────────────────
    $log("Connecting to ssl://{$host}:{$port}");
    $ctx = stream_context_create([
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]
    ]);
    $sock = @stream_socket_client(
        "ssl://{$host}:{$port}", $errno, $errstr, 15,
        STREAM_CLIENT_CONNECT, $ctx
    );
    if (!$sock) {
        $log("Connection failed: [{$errno}] {$errstr}");
        return false;
    }
    stream_set_timeout($sock, 15);

    $r = static function() use ($sock, $log): string {
        $line = (string) fgets($sock, 512);
        $log("< " . rtrim($line));
        return $line;
    };
    $w = static function(string $s) use ($sock, $log): void {
        // Mask password in logs
        $display = preg_match('/^[A-Za-z0-9+\/=]{20,}$/', $s) ? '(base64)' : $s;
        $log("> {$display}");
        fwrite($sock, $s . "\r\n");
    };

    // ── SMTP dialog ───────────────────────────────────────────────
    $r();                                          // 220 greeting
    $w('EHLO ' . (gethostname() ?: 'localhost'));
    while (($line = $r()) !== false             // consume multi-line EHLO
        && strlen($line) > 3 && $line[3] === '-') {}

    $w('AUTH LOGIN');
    $r();                                          // 334 Username
    $w(base64_encode($user));
    $r();                                          // 334 Password
    $w(base64_encode($pass));
    $auth = $r();                                  // 235 or 535
    if (strpos($auth, '235') === false) {
        $log("AUTH failed: {$auth}");
        fclose($sock);
        return false;
    }

    $w("MAIL FROM:<{$from}>");   $r();
    $w("RCPT TO:<{$to}>");       $r();
    $w('DATA');                  $r();             // 354
    $w($data);
    $w('.');
    $result = $r();                                // 250 or error
    $w('QUIT');
    fclose($sock);

    $ok = strpos($result, '250') !== false;
    $log($ok ? "Message accepted." : "Delivery failed: {$result}");
    return $ok;
}
