<?php

declare(strict_types=1);

namespace Wbpms\Infrastructure\Mail;

use RuntimeException;

/**
 * Mailer — thin email-sending adapter.
 *
 * Two drivers are supported via the MAIL_DRIVER env var:
 *
 *   smtp  — sends via PHP's fsockopen/SMTP handshake using the MAIL_HOST,
 *           MAIL_PORT, MAIL_USERNAME, MAIL_PASSWORD, MAIL_ENCRYPTION,
 *           MAIL_FROM_ADDRESS, and MAIL_FROM_NAME env vars.
 *
 *   log   — writes the email to storage/private/mail.log instead of
 *           delivering it; useful for local development without a real
 *           SMTP server.
 *
 * No third-party mail library is used; this keeps the dependency footprint
 * minimal for an internal application. For higher deliverability in
 * production, swap the smtp() method body for a PHPMailer/Symfony Mailer
 * integration.
 *
 * REQ002: forgot-password email delivery.
 */
final class Mailer
{
    private string $driver;
    private string $host;
    private int    $port;
    private string $username;
    private string $password;
    private string $encryption;  // tls | ssl | ''
    private string $fromAddress;
    private string $fromName;

    public function __construct()
    {
        $this->driver      = strtolower(trim((string) ($_ENV['MAIL_DRIVER']       ?? 'log')));
        $this->host        = (string) ($_ENV['MAIL_HOST']         ?? '');
        $this->port        = (int)    ($_ENV['MAIL_PORT']         ?? 587);
        $this->username    = (string) ($_ENV['MAIL_USERNAME']     ?? '');
        $this->password    = (string) ($_ENV['MAIL_PASSWORD']     ?? '');
        $this->encryption  = strtolower(trim((string) ($_ENV['MAIL_ENCRYPTION'] ?? 'tls')));
        $this->fromAddress = (string) ($_ENV['MAIL_FROM_ADDRESS'] ?? 'no-reply@example.com');
        $this->fromName    = (string) ($_ENV['MAIL_FROM_NAME']    ?? 'WBPMS');
    }

    /**
     * Send a plain-text email.
     *
     * @param  string $to      Recipient email address
     * @param  string $subject Email subject line
     * @param  string $body    Plain-text body
     * @throws RuntimeException on SMTP-level failure
     */
    public function send(string $to, string $subject, string $body): void
    {
        match ($this->driver) {
            'smtp' => $this->sendSmtp($to, $subject, $body),
            default => $this->logEmail($to, $subject, $body),
        };
    }

    // -----------------------------------------------------------------------
    // SMTP driver — raw socket SMTP handshake
    // -----------------------------------------------------------------------

    private function sendSmtp(string $to, string $subject, string $body): void
    {
        // Choose socket scheme based on encryption setting.
        $scheme = match ($this->encryption) {
            'ssl'  => 'ssl://',
            default => '',      // plain or STARTTLS (TLS upgrade happens after EHLO)
        };

        $socket = @fsockopen(
            $scheme . $this->host,
            $this->port,
            $errno,
            $errstr,
            10
        );

        if ($socket === false) {
            throw new RuntimeException(
                "Mailer: could not connect to {$this->host}:{$this->port} — {$errstr} ({$errno})"
            );
        }

        try {
            $this->expectCode($socket, 220, 'banner');

            // EHLO
            $this->send_($socket, "EHLO {$this->host}");
            $capabilities = $this->readResponse($socket, 250);

            // STARTTLS upgrade for tls encryption
            if ($this->encryption === 'tls') {
                if (!str_contains($capabilities, 'STARTTLS')) {
                    throw new RuntimeException('Mailer: server does not support STARTTLS');
                }
                $this->send_($socket, 'STARTTLS');
                $this->expectCode($socket, 220, 'STARTTLS');

                if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new RuntimeException('Mailer: TLS handshake failed');
                }

                // Re-send EHLO after TLS upgrade
                $this->send_($socket, "EHLO {$this->host}");
                $this->readResponse($socket, 250);
            }

            // AUTH LOGIN
            if ($this->username !== '') {
                $this->send_($socket, 'AUTH LOGIN');
                $this->expectCode($socket, 334, 'AUTH LOGIN');

                $this->send_($socket, base64_encode($this->username));
                $this->expectCode($socket, 334, 'username');

                $this->send_($socket, base64_encode($this->password));
                $this->expectCode($socket, 235, 'password');
            }

            // MAIL FROM
            $this->send_($socket, "MAIL FROM:<{$this->fromAddress}>");
            $this->expectCode($socket, 250, 'MAIL FROM');

            // RCPT TO
            $this->send_($socket, "RCPT TO:<{$to}>");
            $this->expectCode($socket, 250, 'RCPT TO');

            // DATA
            $this->send_($socket, 'DATA');
            $this->expectCode($socket, 354, 'DATA');

            $messageId = sprintf(
                '<%s@%s>',
                bin2hex(random_bytes(16)),
                $this->host
            );

            $headers  = "From: {$this->fromName} <{$this->fromAddress}>\r\n";
            $headers .= "To: {$to}\r\n";
            $headers .= "Subject: {$subject}\r\n";
            $headers .= "Message-ID: {$messageId}\r\n";
            $headers .= "Date: " . date('r') . "\r\n";
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $headers .= "Content-Transfer-Encoding: 8bit\r\n";

            // Dot-stuff body lines starting with a period (RFC 5321)
            $safeBody = preg_replace('/^\./', '..', $body) ?? $body;

            fwrite($socket, $headers . "\r\n" . $safeBody . "\r\n.\r\n");
            $this->expectCode($socket, 250, 'message accepted');

            // QUIT
            $this->send_($socket, 'QUIT');
        } finally {
            fclose($socket);
        }
    }

    /**
     * Write a line to the SMTP socket.
     *
     * @param resource $socket
     */
    private function send_(mixed $socket, string $line): void
    {
        fwrite($socket, $line . "\r\n");
    }

    /**
     * Read a multi-line SMTP response and return its body.
     * Throws if the first status code does not match $expected.
     *
     * @param  resource $socket
     */
    private function readResponse(mixed $socket, int $expected): string
    {
        $response = '';
        while (!feof($socket)) {
            $line = fgets($socket, 512);
            if ($line === false) {
                break;
            }
            $response .= $line;
            // A line whose 4th character is a space is the last line.
            if (strlen($line) >= 4 && $line[3] === ' ') {
                break;
            }
        }

        $code = (int) substr($response, 0, 3);
        if ($code !== $expected) {
            throw new RuntimeException(
                "Mailer: expected SMTP {$expected}, got {$code}. Response: " . trim($response)
            );
        }

        return $response;
    }

    /**
     * @param resource $socket
     */
    private function expectCode(mixed $socket, int $expected, string $context): void
    {
        $this->readResponse($socket, $expected);
    }

    // -----------------------------------------------------------------------
    // Log driver — writes email to storage/private/mail.log
    // -----------------------------------------------------------------------

    private function logEmail(string $to, string $subject, string $body): void
    {
        $logPath = defined('APP_ROOT')
            ? APP_ROOT . '/storage/private/mail.log'
            : __DIR__ . '/../../../storage/private/mail.log';

        $separator = str_repeat('-', 72);
        $entry = <<<LOG

        {$separator}
        Date:    {$this->timestamp()}
        From:    {$this->fromName} <{$this->fromAddress}>
        To:      {$to}
        Subject: {$subject}
        {$separator}
        {$body}
        {$separator}
        LOG;

        file_put_contents($logPath, $entry . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    private function timestamp(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone('Asia/Manila')))
            ->format('Y-m-d H:i:s T');
    }
}
