<?php

declare(strict_types=1);

namespace Wbpms\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Wbpms\Infrastructure\Mail\Mailer;

/**
 * Unit tests for Mailer — smtp and log drivers.
 *
 * Supported drivers: smtp (Gmail App Password / any SMTP server) and log.
 * Any other MAIL_DRIVER value must throw a RuntimeException immediately so
 * misconfigured environments fail loudly rather than silently dropping email.
 *
 * REQ002: forgot-password email delivery.
 */
final class MailerTest extends TestCase
{
    // -----------------------------------------------------------------------
    // Teardown — unset injected env vars
    // -----------------------------------------------------------------------

    protected function tearDown(): void
    {
        foreach ([
            'MAIL_DRIVER', 'MAIL_FROM_ADDRESS', 'MAIL_FROM_NAME',
            'MAIL_HOST', 'MAIL_PORT',
            'MAIL_USERNAME', 'MAIL_PASSWORD', 'MAIL_ENCRYPTION',
        ] as $key) {
            unset($_ENV[$key]);
        }
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function setLogEnv(): void
    {
        $_ENV['MAIL_DRIVER']       = 'log';
        $_ENV['MAIL_FROM_ADDRESS'] = 'sender@example.com';
        $_ENV['MAIL_FROM_NAME']    = 'WBPMS';
    }

    // -----------------------------------------------------------------------
    // Log driver
    // -----------------------------------------------------------------------

    /** @test */
    public function log_driver_writes_email_without_throwing(): void
    {
        $this->setLogEnv();

        $mailer = new Mailer();

        // send() on log driver must not throw.
        try {
            $mailer->send('to@example.com', 'Test Subject', 'Body text.');
            $threw = false;
        } catch (\Throwable) {
            $threw = true;
        }

        self::assertFalse($threw, 'log driver must not throw');
    }

    // -----------------------------------------------------------------------
    // Unsupported driver
    // -----------------------------------------------------------------------

    /** @test */
    public function unsupported_driver_throws_runtime_exception(): void
    {
        $_ENV['MAIL_DRIVER'] = 'mailgun';

        $mailer = new Mailer();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches("/unsupported driver 'mailgun'/");

        $mailer->send('r@example.com', 'S', 'B');
    }

    /** @test */
    public function unsupported_driver_error_lists_supported_drivers(): void
    {
        $_ENV['MAIL_DRIVER'] = 'sendgrid';

        $mailer = new Mailer();

        try {
            $mailer->send('r@example.com', 'S', 'B');
            self::fail('Expected RuntimeException was not thrown');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('smtp', $e->getMessage());
            self::assertStringContainsString('log', $e->getMessage());
        }
    }

    /** @test */
    public function unsupported_driver_does_not_expose_credentials_in_exception(): void
    {
        $_ENV['MAIL_DRIVER']   = 'unknown_provider';
        $_ENV['MAIL_PASSWORD'] = 'SECRET_CREDENTIAL';

        $mailer = new Mailer();

        try {
            $mailer->send('r@example.com', 'S', 'B');
            self::fail('Expected RuntimeException was not thrown');
        } catch (RuntimeException $e) {
            self::assertStringNotContainsString(
                'SECRET_CREDENTIAL',
                $e->getMessage(),
                'Credentials must not appear in the exception message'
            );
        }
    }
}
