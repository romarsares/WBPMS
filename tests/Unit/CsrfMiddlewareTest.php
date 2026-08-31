<?php

declare(strict_types=1);

namespace Wbpms\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Wbpms\Http\Middleware\CsrfMiddleware;

/**
 * Unit tests for CsrfMiddleware.
 *
 * ADR-0001: mutating browser requests require CSRF tokens.
 * Tests token generation, retrieval, invalidation, and the hidden field helper.
 *
 * Note: CsrfMiddleware::verify() calls exit() on failure in HTTP context;
 * under CLI SAPI it returns early. Therefore we test token state directly
 * rather than testing the verify() side-effects.
 */
final class CsrfMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    /**
     * @test
     * generateToken() creates a non-empty hex token.
     */
    public function it_generates_a_non_empty_token(): void
    {
        $token = CsrfMiddleware::generateToken();

        $this->assertNotEmpty($token);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token, 'Token should be 64 hex chars (32 bytes)');
    }

    /**
     * @test
     * generateToken() returns the same token on subsequent calls within a session.
     */
    public function it_returns_same_token_on_repeated_calls(): void
    {
        $token1 = CsrfMiddleware::generateToken();
        $token2 = CsrfMiddleware::generateToken();

        $this->assertSame($token1, $token2, 'Token should be stable within a session');
    }

    /**
     * @test
     * generateToken() stores the token in $_SESSION.
     */
    public function it_stores_token_in_session(): void
    {
        $token = CsrfMiddleware::generateToken();

        $this->assertArrayHasKey('_csrf_token', $_SESSION);
        $this->assertSame($token, $_SESSION['_csrf_token']);
    }

    /**
     * @test
     * invalidate() removes the token from the session.
     */
    public function it_removes_token_on_invalidate(): void
    {
        CsrfMiddleware::generateToken(); // seed token
        $this->assertArrayHasKey('_csrf_token', $_SESSION);

        CsrfMiddleware::invalidate();

        $this->assertArrayNotHasKey('_csrf_token', $_SESSION);
    }

    /**
     * @test
     * field() returns an HTML hidden input containing the current token.
     */
    public function it_generates_hidden_field_with_token(): void
    {
        $token = CsrfMiddleware::generateToken();
        $field = CsrfMiddleware::field();

        $this->assertStringContainsString('type="hidden"', $field);
        $this->assertStringContainsString('name="_csrf_token"', $field);
        $this->assertStringContainsString($token, $field);
    }

    /**
     * @test
     * Two separate sessions produce different tokens.
     */
    public function it_produces_different_tokens_across_sessions(): void
    {
        $_SESSION = [];
        $token1 = CsrfMiddleware::generateToken();

        $_SESSION = [];
        $token2 = CsrfMiddleware::generateToken();

        $this->assertNotSame($token1, $token2, 'Different sessions should have different tokens');
    }

    /**
     * @test
     * verify() under CLI SAPI returns without exiting (no session to check).
     */
    public function verify_returns_safely_under_cli(): void
    {
        // Under CLI, verify() should return without calling exit()
        // If it did call exit(), this test would terminate the process.
        CsrfMiddleware::verify();

        // If we reach this line, verify() returned safely.
        $this->assertTrue(true);
    }
}
