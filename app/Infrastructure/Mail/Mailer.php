<?php

declare(strict_types=1);

namespace Wbpms\Infrastructure\Mail;

use RuntimeException;

/**
 * Mailer — thin email-sending adapter.
 *
 * Three drivers are supported via the MAIL_DRIVER env var:
 *
 *   smtp   — sends via PHP's fsockopen/SMTP handshake using the MAIL_HOST,
 *            MAIL_PORT, MAIL_USERNAME, MAIL_PASSWORD, MAIL_ENCRYPTION,
 *            MAIL_FROM_ADDRESS, and MAIL_FROM_NAME env vars.
 *
 *   resend — sends via the Resend REST API over HTTPS (port 443).
 *            Required env vars: RESEND_API_KEY, MAIL_FROM_ADDRESS,
 *            MAIL_FROM_NAME. This driver works on Railway Hobby, which
 *            blocks outbound SMTP ports 25/465/587 but allows port 443.
 *
 *   log    — writes the email to storage/private/mail.log instead of
 *            delivering it; useful for local development without a real
 *            SMTP server or API key.
 *
 * No third-party mail library is used; this keeps the dependency footprint
 * minimal for an internal application.
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
    private string $resendApiKey;

    public function __construct()
    {
        $this->driver       = strtolower(trim((string) ($_ENV['MAIL_DRIVER']       ?? 'log')));
        $this->host         = (string) ($_ENV['MAIL_HOST']         ?? '');
        $this->port         = (int)    ($_ENV['MAIL_PORT']         ?? 587);
        $this->username     = (string) ($_ENV['MAIL_USERNAME']     ?? '');
        $this->password     = $this->stripQuotes((string) ($_ENV['MAIL_PASSWORD'] ?? ''));
        $this->encryption   = strtolower(trim((string) ($_ENV['MAIL_ENCRYPTION']  ?? 'tls')));
        $this->fromAddress  = (string) ($_ENV['MAIL_FROM_ADDRESS'] ?? 'no-reply@example.com');
        $this->fromName     = (string) ($_ENV['MAIL_FROM_NAME']    ?? 'WBPMS');
        $this->resendApiKey = (string) ($_ENV['RESEND_API_KEY']    ?? '');
    }

    /**
     * Strip surrounding single or double quotes from a password value.
     *
     * When a Gmail App Password like "xxxx xxxx xxxx xxxx" is set via a
     * .env file the phpdotenv library strips the quotes automatically.
     * However, when the value is pasted directly into Railway (or another
     * hosting dashboard) as a raw environment variable, the quotes are
     * preserved as literal characters and break AUTH LOGIN.
     *
     * This method normalises both cases so the code works identically
     * whether the password was sourced from .env or from a hosting dashboard.
     */
    private function stripQuotes(string $value): string
    {
        if (strlen($value) >= 2) {
            $first = $value[0];
            $last  = $value[strlen($value) - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                return substr($value, 1, -1);
            }
        }

        return $value;
    }

    /**
     * Send a plain-text email.
     *
     * Dispatches to the correct driver based on MAIL_DRIVER:
     *   smtp   — raw-socket SMTP handshake (local/Gmail/Brevo)
     *   resend — Resend REST API over HTTPS port 443 (Railway-compatible)
     *   log    — write to storage/private/mail.log (default / local dev)
     *
     * On driver-level failure the error is written to the log and the
     * exception is re-thrown so callers (e.g. PasswordResetService) can
     * surface a useful message rather than showing a 500 page.
     *
     * @param  string $to      Recipient email address
     * @param  string $subject Email subject line
     * @param  string $body    Plain-text body
     * @throws RuntimeException on delivery failure (smtp or resend drivers)
     */
    public function send(string $to, string $subject, string $body): void
    {
        if ($this->driver === 'smtp') {
            try {
                $this->sendSmtp($to, $subject, $body);
            } catch (RuntimeException $e) {
                // Always log the failure so it is traceable even in production.
                $this->logEmail(
                    $to,
                    $subject,
                    "[SMTP DELIVERY FAILED — {$e->getMessage()}]\n\n{$body}"
                );
                // Re-throw so PasswordResetService (and any other caller) can
                // catch it and decide whether to surface an error or swallow it.
                throw $e;
            }

            return;
        }

        if ($this->driver === 'resend') {
            try {
                $this->sendResend($to, $subject, $body);
            } catch (RuntimeException $e) {
                $this->logEmail(
                    $to,
                    $subject,
                    "[RESEND DELIVERY FAILED — {$e->getMessage()}]\n\n{$body}"
                );
                throw $e;
            }

            return;
        }

        $this->logEmail($to, $subject, $body);
    }

    // -----------------------------------------------------------------------
    // Resend driver — REST API over HTTPS port 443
    // -----------------------------------------------------------------------

    /**
     * Send a plain-text email via the Resend REST API.
     *
     * Endpoint: POST https://api.resend.com/emails
     * Auth:     Bearer token from RESEND_API_KEY env var.
     *
     * The Resend API uses HTTPS on port 443, which is allowed on Railway
     * Hobby. No third-party SDK is needed; a single curl call suffices.
     *
     * @throws RuntimeException when curl fails or the API returns a non-2xx
     *                          status code. The raw API error message is
     *                          included in the exception but the API key is
     *                          never exposed in the message or logs.
     */
    private function sendResend(string $to, string $subject, string $body): void
    {
        if ($this->resendApiKey === '') {
            throw new RuntimeException(
                'Mailer: RESEND_API_KEY is not configured'
            );
        }

        if (!function_exists('curl_init')) {
            throw new RuntimeException(
                'Mailer: the curl extension is required for the resend driver'
            );
        }

        $payload = json_encode([
            'from'    => "{$this->fromName} <{$this->fromAddress}>",
            'to'      => [$to],
            'subject' => $subject,
            'text'    => $body,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($payload === false) {
            throw new RuntimeException('Mailer: failed to encode Resend request payload');
        }

        $ch = curl_init('https://api.resend.com/emails');

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $this->resendApiKey,
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_CONNECTTIMEOUT => 10,
            // Enforce TLS certificate verification — never disable in production.
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $responseBody = curl_exec($ch);
        $httpStatus   = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError    = curl_error($ch);
        curl_close($ch);

        if ($responseBody === false || $curlError !== '') {
            throw new RuntimeException(
                'Mailer: Resend API request failed (curl error: ' . $curlError . ')'
            );
        }

        // Resend returns 200 or 201 on success.
        if ($httpStatus < 200 || $httpStatus >= 300) {
            // Extract a safe error message from the JSON response.
            // Never log the full payload (it could echo request data).
            $decoded = json_decode((string) $responseBody, true);
            $apiMessage = is_array($decoded) && isset($decoded['message'])
                ? (string) $decoded['message']
                : "HTTP {$httpStatus}";

            throw new RuntimeException(
                "Mailer: Resend API returned {$httpStatus} — {$apiMessage}"
            );
        }
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
