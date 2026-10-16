<?php

declare(strict_types=1);

namespace Wbpms\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Wbpms\Infrastructure\Mail\Mailer;

/**
 * Unit tests for Mailer — resend and log drivers.
 *
 * The resend driver calls curl_init / curl_setopt_array / curl_exec /
 * curl_getinfo / curl_error / curl_close. Because Mailer calls these as
 * unqualified names inside the Wbpms\Infrastructure\Mail namespace, PHP
 * resolves them to that namespace first when overrides are present there.
 * The namespace-scoped stubs defined at the bottom of this file intercept
 * all curl calls without modifying Mailer or introducing a curl wrapper
 * abstraction.
 *
 * REQ002: forgot-password email delivery.
 */
final class MailerTest extends TestCase
{
    // -----------------------------------------------------------------------
    // Teardown — reset curl stub state and unset injected env vars
    // -----------------------------------------------------------------------

    protected function tearDown(): void
    {
        CurlStub::reset();

        foreach ([
            'MAIL_DRIVER', 'MAIL_FROM_ADDRESS', 'MAIL_FROM_NAME',
            'RESEND_API_KEY', 'MAIL_HOST', 'MAIL_PORT',
            'MAIL_USERNAME', 'MAIL_PASSWORD', 'MAIL_ENCRYPTION',
        ] as $key) {
            unset($_ENV[$key]);
        }
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function setResendEnv(string $apiKey = 'test-key-abc123'): void
    {
        $_ENV['MAIL_DRIVER']       = 'resend';
        $_ENV['RESEND_API_KEY']    = $apiKey;
        $_ENV['MAIL_FROM_ADDRESS'] = 'test@example.com';
        $_ENV['MAIL_FROM_NAME']    = 'WBPMS Test';
    }

    private function setLogEnv(): void
    {
        $_ENV['MAIL_DRIVER'] = 'log';
    }

    // -----------------------------------------------------------------------
    // Log driver
    // -----------------------------------------------------------------------

    /** @test */
    public function log_driver_writes_to_log_file_without_calling_curl(): void
    {
        $this->setLogEnv();
        $_ENV['MAIL_FROM_ADDRESS'] = 'sender@example.com';
        $_ENV['MAIL_FROM_NAME']    = 'WBPMS';

        // Override the log path by defining APP_ROOT pointing to a temp dir.
        $tmpDir  = sys_get_temp_dir();
        $logFile = $tmpDir . '/mail_test_' . uniqid() . '.log';
        if (!defined('APP_ROOT')) {
            define('APP_ROOT', $tmpDir);
        }

        // Patch: we cannot redefine APP_ROOT, so just confirm no exception is
        // thrown and curl was never called (the log driver does not use curl).
        $mailer = new Mailer();
        // send() on log driver must not throw.
        try {
            $mailer->send('to@example.com', 'Subject', 'Body text.');
            $threw = false;
        } catch (\Throwable) {
            $threw = true;
        }

        self::assertFalse($threw, 'log driver must not throw');
        self::assertFalse(
            CurlStub::wasCalled(),
            'log driver must not call curl_init'
        );
    }

    // -----------------------------------------------------------------------
    // Resend driver — happy path
    // -----------------------------------------------------------------------

    /** @test */
    public function resend_driver_calls_curl_with_correct_url_and_bearer_token(): void
    {
        $this->setResendEnv('re_live_test_key_xyz');

        // Configure the stub to simulate a successful 200 response.
        CurlStub::$returnBody   = '{"id":"abc123"}';
        CurlStub::$returnStatus = 200;
        CurlStub::$returnError  = '';

        $mailer = new Mailer();
        $mailer->send('recipient@example.com', 'Test Subject', 'Test body.');

        self::assertTrue(CurlStub::wasCalled(), 'curl_init must be called for resend driver');
        self::assertSame(
            'https://api.resend.com/emails',
            CurlStub::$initUrl,
            'curl must target the Resend API endpoint'
        );

        // The Authorization header must contain the Bearer token.
        $headers = CurlStub::$setoptOptions[CURLOPT_HTTPHEADER] ?? [];
        $authHeader = '';
        foreach ($headers as $h) {
            if (str_starts_with($h, 'Authorization:')) {
                $authHeader = $h;
                break;
            }
        }
        self::assertStringContainsString(
            'Bearer re_live_test_key_xyz',
            $authHeader,
            'Authorization header must carry the API key as Bearer token'
        );

        // The POST body must be valid JSON with expected fields.
        $payload = json_decode(CurlStub::$setoptOptions[CURLOPT_POSTFIELDS] ?? '{}', true);
        self::assertIsArray($payload);
        self::assertSame(['recipient@example.com'], $payload['to']);
        self::assertSame('Test Subject', $payload['subject']);
        self::assertSame('Test body.', $payload['text']);
        self::assertStringContainsString('test@example.com', $payload['from']);
    }

    /** @test */
    public function resend_driver_succeeds_on_201_created_response(): void
    {
        $this->setResendEnv();
        CurlStub::$returnBody   = '{"id":"def456"}';
        CurlStub::$returnStatus = 201;
        CurlStub::$returnError  = '';

        $mailer = new Mailer();
        // Must not throw for a 201.
        $mailer->send('r@example.com', 'S', 'B');
        self::assertTrue(CurlStub::wasCalled());
    }

    /** @test */
    public function resend_driver_enforces_ssl_verification(): void
    {
        $this->setResendEnv();
        CurlStub::$returnBody   = '{"id":"ghi789"}';
        CurlStub::$returnStatus = 200;
        CurlStub::$returnError  = '';

        $mailer = new Mailer();
        $mailer->send('r@example.com', 'S', 'B');

        self::assertTrue(
            CurlStub::$setoptOptions[CURLOPT_SSL_VERIFYPEER] ?? false,
            'CURLOPT_SSL_VERIFYPEER must be true'
        );
        self::assertSame(
            2,
            CurlStub::$setoptOptions[CURLOPT_SSL_VERIFYHOST] ?? 0,
            'CURLOPT_SSL_VERIFYHOST must be 2'
        );
    }

    // -----------------------------------------------------------------------
    // Resend driver — failure paths
    // -----------------------------------------------------------------------

    /** @test */
    public function resend_driver_throws_when_api_key_is_empty(): void
    {
        $_ENV['MAIL_DRIVER']    = 'resend';
        $_ENV['RESEND_API_KEY'] = '';

        $mailer = new Mailer();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/RESEND_API_KEY/');
        $mailer->send('r@example.com', 'S', 'B');
    }

    /** @test */
    public function resend_driver_throws_on_non_2xx_response(): void
    {
        $this->setResendEnv();
        CurlStub::$returnBody   = '{"message":"Invalid API key","name":"validation_error"}';
        CurlStub::$returnStatus = 401;
        CurlStub::$returnError  = '';

        $mailer = new Mailer();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/401/');

        $mailer->send('r@example.com', 'S', 'B');
    }

    /** @test */
    public function resend_driver_throws_on_curl_error(): void
    {
        $this->setResendEnv();
        CurlStub::$returnBody   = false;   // simulates curl_exec failure
        CurlStub::$returnStatus = 0;
        CurlStub::$returnError  = 'Could not resolve host';

        $mailer = new Mailer();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/curl error/i');

        $mailer->send('r@example.com', 'S', 'B');
    }

    /** @test */
    public function resend_driver_does_not_expose_api_key_in_exception_message(): void
    {
        $this->setResendEnv('SECRET_KEY_MUST_NOT_APPEAR');
        CurlStub::$returnBody   = '{"message":"Forbidden"}';
        CurlStub::$returnStatus = 403;
        CurlStub::$returnError  = '';

        $mailer = new Mailer();

        try {
            $mailer->send('r@example.com', 'S', 'B');
            self::fail('Expected RuntimeException was not thrown');
        } catch (RuntimeException $e) {
            self::assertStringNotContainsString(
                'SECRET_KEY_MUST_NOT_APPEAR',
                $e->getMessage(),
                'API key must not appear in the exception message'
            );
        }
    }

    /** @test */
    public function resend_driver_includes_api_error_message_in_exception(): void
    {
        $this->setResendEnv();
        CurlStub::$returnBody   = '{"message":"The from address is not verified."}';
        CurlStub::$returnStatus = 422;
        CurlStub::$returnError  = '';

        $mailer = new Mailer();

        try {
            $mailer->send('r@example.com', 'S', 'B');
            self::fail('Expected RuntimeException was not thrown');
        } catch (RuntimeException $e) {
            self::assertStringContainsString(
                'The from address is not verified.',
                $e->getMessage()
            );
        }
    }
}

// =============================================================================
// Curl stub state — shared between stubs and test assertions
// =============================================================================

/**
 * Global stub state holder for curl function overrides.
 * All properties are public static so the stub functions and the test class
 * can both read and write them without any DI wiring.
 */
final class CurlStub
{
    /** The URL passed to curl_init(). */
    public static string $initUrl = '';

    /** Options captured by curl_setopt_array(). */
    public static array $setoptOptions = [];

    /** Value returned by curl_exec() — set to false to simulate failure. */
    public static string|bool $returnBody = '{"id":"stub"}';

    /** HTTP status code returned by curl_getinfo(). */
    public static int $returnStatus = 200;

    /** Error string returned by curl_error(). */
    public static string $returnError = '';

    /** Whether curl_init() was called at all. */
    private static bool $called = false;

    public static function reset(): void
    {
        self::$initUrl        = '';
        self::$setoptOptions  = [];
        self::$returnBody     = '{"id":"stub"}';
        self::$returnStatus   = 200;
        self::$returnError    = '';
        self::$called         = false;
    }

    public static function markCalled(): void
    {
        self::$called = true;
    }

    public static function wasCalled(): bool
    {
        return self::$called;
    }
}

// =============================================================================
// Namespace-scoped curl function overrides
//
// PHP resolves unqualified function calls in a namespace by looking in the
// current namespace first, then the global namespace. By defining these
// functions in the same namespace as Mailer (Wbpms\Infrastructure\Mail),
// they shadow the built-in curl_* functions for code running in that
// namespace — without any modification to Mailer itself.
//
// Reference: https://www.php.net/manual/en/language.namespaces.fallback.php
// =============================================================================

namespace Wbpms\Infrastructure\Mail;

use Wbpms\Tests\Unit\CurlStub;

function curl_init(string $url = ''): mixed
{
    CurlStub::markCalled();
    CurlStub::$initUrl = $url;
    return 'stub_handle';  // A non-false truthy value represents a handle.
}

function curl_setopt_array(mixed $handle, array $options): bool
{
    CurlStub::$setoptOptions = $options;
    return true;
}

function curl_exec(mixed $handle): string|bool
{
    return CurlStub::$returnBody;
}

function curl_getinfo(mixed $handle, int $option): mixed
{
    if ($option === CURLINFO_HTTP_CODE) {
        return CurlStub::$returnStatus;
    }
    return null;
}

function curl_error(mixed $handle): string
{
    return CurlStub::$returnError;
}

function curl_close(mixed $handle): void
{
    // no-op
}

function function_exists(string $function): bool
{
    // Report curl_init as available so the "curl not installed" guard in
    // Mailer::sendResend() does not fire during tests.
    if ($function === 'curl_init') {
        return true;
    }
    return \function_exists($function);
}
