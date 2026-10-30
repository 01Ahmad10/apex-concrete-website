<?php
/**
 * Apex Concrete Polishing — enquiry form handler.
 *
 * Receives the contact form (contact.html) and emails it to the Apex mailbox
 * through Hostinger SMTP (smtp.hostinger.com) using the settings in
 * mail-config.php. If no SMTP password is configured it falls back to PHP mail().
 */
declare(strict_types=1);

date_default_timezone_set('Australia/Sydney');

$config = [
    'to'                => 'info@apexconcretepolishing.com.au',
    'from'              => 'info@apexconcretepolishing.com.au',
    'from_name'         => 'Apex Concrete Polishing',
    'smtp_host'         => 'smtp.hostinger.com',
    'smtp_port'         => 465,
    'smtp_user'         => 'info@apexconcretepolishing.com.au',
    'smtp_pass'         => '',
    'send_confirmation' => true,
];
$configFile = __DIR__ . '/mail-config.php';
if (is_file($configFile)) {
    $custom = require $configFile;
    if (is_array($custom)) {
        $config = array_merge($config, $custom);
    }
}

$wantsJson = str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');

function respond(bool $ok, string $message, int $status = 200): void
{
    global $wantsJson;
    if ($wantsJson) {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store');
        echo json_encode(['ok' => $ok, 'message' => $message], JSON_UNESCAPED_UNICODE);
    } else {
        header('Location: contact.html?' . ($ok ? 'sent=1' : 'error=1') . '#contact', true, 303);
    }
    exit;
}

function address(string $name, string $email): string
{
    $name = trim(str_replace(['"', '\\'], '', $name));
    if ($name === '') {
        return '<' . $email . '>';
    }
    return encode_header($name, true) . ' <' . $email . '>';
}

function encode_header(string $text, bool $quoteAscii = false): string
{
    if (preg_match('/[^\x20-\x7E]/', $text)) {
        return '=?UTF-8?B?' . base64_encode($text) . '?=';
    }
    return $quoteAscii ? '"' . $text . '"' : $text;
}

function send_mail(array $c, string $to, string $subject, string $body, string $replyTo, string $replyName): void
{
    $domain  = substr(strrchr($c['from'], '@') ?: '@localhost', 1);
    $headers = [
        'Date: ' . date(DATE_RFC2822),
        'From: ' . address($c['from_name'], $c['from']),
        'Reply-To: ' . address($replyName, $replyTo),
        'To: <' . $to . '>',
        'Subject: ' . encode_header($subject),
        'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $domain . '>',
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
        'X-Mailer: Apex website form',
    ];
    $body = str_replace(["\r\n", "\r"], "\n", $body);

    if ($c['smtp_pass'] !== '') {
        smtp_send($c, $to, $headers, str_replace("\n", "\r\n", $body));
        return;
    }

    // Fallback: PHP mail(). It sets To/Subject itself.
    $mailHeaders = array_filter($headers, static fn (string $h): bool => !str_starts_with($h, 'To:') && !str_starts_with($h, 'Subject:'));
    if (!mail($to, encode_header($subject), $body, implode("\r\n", $mailHeaders), '-f' . $c['from'])) {
        throw new RuntimeException('mail() returned false');
    }
}

function smtp_send(array $c, string $to, array $headers, string $body): void
{
    $port        = (int) $c['smtp_port'];
    $implicitTls = $port === 465;
    $sock        = @stream_socket_client(($implicitTls ? 'ssl://' : 'tcp://') . $c['smtp_host'] . ':' . $port, $errno, $errstr, 20);
    if (!$sock) {
        throw new RuntimeException("SMTP connection failed ($errno): $errstr");
    }
    stream_set_timeout($sock, 20);

    $read = static function () use ($sock): string {
        $reply = '';
        while (($line = fgets($sock, 1024)) !== false) {
            $reply .= $line;
            if (!isset($line[3]) || $line[3] !== '-') {
                break;
            }
        }
        return $reply;
    };
    $expect = static function (string $reply, array $codes, string $step): void {
        if (!in_array((int) substr($reply, 0, 3), $codes, true)) {
            throw new RuntimeException("SMTP $step failed: " . trim($reply));
        }
    };
    $send = static function (string $cmd, array $codes, string $step) use ($sock, $read, $expect): void {
        fwrite($sock, $cmd . "\r\n");
        $expect($read(), $codes, $step);
    };

    $expect($read(), [220], 'greeting');
    $ehlo = 'EHLO ' . preg_replace('/[^A-Za-z0-9.-]/', '', $_SERVER['SERVER_NAME'] ?? 'localhost');
    $send($ehlo, [250], 'EHLO');
    if (!$implicitTls) {
        $send('STARTTLS', [220], 'STARTTLS');
        if (!stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            throw new RuntimeException('SMTP TLS negotiation failed');
        }
        $send($ehlo, [250], 'EHLO');
    }
    $send('AUTH LOGIN', [334], 'AUTH');
    $send(base64_encode($c['smtp_user']), [334], 'AUTH username');
    $send(base64_encode($c['smtp_pass']), [235], 'AUTH password');
    $send('MAIL FROM:<' . $c['from'] . '>', [250], 'MAIL FROM');
    $send('RCPT TO:<' . $to . '>', [250, 251], 'RCPT TO');
    $send('DATA', [354], 'DATA');
    $data = implode("\r\n", $headers) . "\r\n\r\n" . preg_replace('/^\./m', '..', $body);
    $send($data . "\r\n.", [250], 'message');
    fwrite($sock, "QUIT\r\n");
    fclose($sock);
}

// ---------------------------------------------------------------------------

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Method not allowed');
}

$field = static function (string $key, int $max): string {
    $value = trim((string) ($_POST[$key] ?? ''));
    $value = preg_replace('/[\r\n\t]+/', ' ', $value) ?? '';
    return mb_substr($value, 0, $max);
};

// Honeypot: real visitors never see this field. Pretend success so bots move on.
if ($field('website', 20) !== '') {
    respond(true, 'Thank you — your enquiry has been sent. We’ll be in touch shortly.');
}

$name     = $field('name', 120);
$email    = $field('email', 200);
$phone    = $field('phone', 60);
$location = $field('location', 160);
$service  = $field('service', 100);
$message  = mb_substr(trim((string) ($_POST['message'] ?? '')), 0, 5000);

if ($name === '' || $email === '' || $location === '' || $message === '') {
    respond(false, 'Please fill in your name, email, project location and a few details about your project.', 422);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond(false, 'Please enter a valid email address.', 422);
}

$message = wordwrap($message, 900, "\n", true);
$details = [
    'Name:      ' . $name,
    'Email:     ' . $email,
    'Phone:     ' . ($phone !== '' ? $phone : '—'),
    'Location:  ' . $location,
    'Service:   ' . ($service !== '' ? $service : '—'),
    '',
    'Project details:',
    $message,
];

$subject = 'Website enquiry — ' . ($service !== '' ? $service : 'Concrete flooring') . ' — ' . $name;
$body    = implode("\n", array_merge(
    ['New enquiry from the Apex Concrete Polishing website', ''],
    $details,
    ['', '—', 'Sent ' . date('j M Y, g:i a') . ' from ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown IP')]
));

try {
    send_mail($config, $config['to'], $subject, $body, $email, $name);
} catch (Throwable $e) {
    error_log('[apex enquiry] ' . $e->getMessage());
    respond(false, 'Sorry, your enquiry could not be sent right now. Please email info@apexconcretepolishing.com.au or call 0403 383 616.', 500);
}

if (!empty($config['send_confirmation'])) {
    $confirmation = implode("\n", array_merge(
        [
            'Hi ' . $name . ',',
            '',
            'Thanks for getting in touch with Apex Concrete Polishing. We’ve received your enquiry and will be in touch shortly.',
            '',
            'Here is a copy of what you sent:',
            '',
        ],
        $details,
        [
            '',
            'If you need to reach us sooner, call 0403 383 616 or reply to this email.',
            '',
            'Apex Concrete Polishing',
            'https://apexconcretepolishing.com.au',
        ]
    ));
    try {
        send_mail($config, $email, 'We’ve received your enquiry — Apex Concrete Polishing', $confirmation, $config['to'], $config['from_name']);
    } catch (Throwable $e) {
        error_log('[apex enquiry] confirmation: ' . $e->getMessage());
    }
}

respond(true, 'Thank you — your enquiry has been sent. We’ll be in touch shortly.');
