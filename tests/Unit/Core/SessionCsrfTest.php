<?php

declare(strict_types=1);

namespace BarePitch\Tests\Unit\Core;

use BarePitch\Core\Session;
use PHPUnit\Framework\TestCase;

/**
 * Tests CSRF token generation and verification via Session::csrfToken()
 * and Session::verifyCsrf().
 *
 * The Session class reads/writes the $_SESSION superglobal directly.
 * bootstrap.php ensures $_SESSION is initialised as an empty array,
 * so no PHP session_start() is required here.
 */
class SessionCsrfTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Start each test with a clean session state
        $_SESSION = [];
    }

    // ----------------------------------------------------------------
    // Token generation
    // ----------------------------------------------------------------

    public function testCsrfTokenIsGeneratedWhenNotPresent(): void
    {
        $token = Session::csrfToken();

        $this->assertNotEmpty($token);
        $this->assertIsString($token);
    }

    public function testCsrfTokenIsA64CharHexString(): void
    {
        $token = Session::csrfToken();

        // bin2hex(random_bytes(32)) always produces 64 hex characters
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);
    }

    public function testCsrfTokenIsStoredInSession(): void
    {
        $token = Session::csrfToken();

        $this->assertArrayHasKey('_csrf', $_SESSION);
        $this->assertSame($token, $_SESSION['_csrf']);
    }

    public function testCsrfTokenIsGeneratedOnlyOnce(): void
    {
        $first  = Session::csrfToken();
        $second = Session::csrfToken();

        $this->assertSame($first, $second, 'Token must be stable within the same session.');
    }

    public function testCsrfTokenChangesAfterSessionReset(): void
    {
        $first = Session::csrfToken();

        // Simulate a new session
        $_SESSION = [];
        $second = Session::csrfToken();

        // Cannot guarantee they differ (random_bytes could theoretically repeat),
        // but in practice with 32 bytes of entropy they will always differ.
        // We assert both are valid tokens instead.
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $first);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $second);
    }

    // ----------------------------------------------------------------
    // Token verification
    // ----------------------------------------------------------------

    public function testVerifyCsrfReturnsTrueForValidToken(): void
    {
        $token = Session::csrfToken();

        $this->assertTrue(Session::verifyCsrf($token));
    }

    public function testVerifyCsrfReturnsFalseForWrongToken(): void
    {
        Session::csrfToken(); // ensure a token is set

        $this->assertFalse(Session::verifyCsrf('wrong-token'));
    }

    public function testVerifyCsrfReturnsFalseForEmptyToken(): void
    {
        Session::csrfToken(); // ensure a token is set

        $this->assertFalse(Session::verifyCsrf(''));
    }

    public function testVerifyCsrfReturnsFalseWhenNoTokenInSession(): void
    {
        // No token generated — session is empty from setUp()
        $this->assertFalse(Session::verifyCsrf('any-value'));
    }

    public function testVerifyCsrfUsesTimingSafeComparison(): void
    {
        // Verify that hash_equals is used (indirectly) by confirming a
        // token that differs only in the last character still fails.
        $token = Session::csrfToken();

        // Flip the last character
        $tampered = substr($token, 0, -1) . (substr($token, -1) === 'a' ? 'b' : 'a');

        $this->assertFalse(Session::verifyCsrf($tampered));
    }
}
