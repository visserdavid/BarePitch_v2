<?php

declare(strict_types=1);

namespace BarePitch\Tests\Feature\Auth;

use BarePitch\Core\Csrf;
use BarePitch\Core\Exceptions\CsrfException;
use BarePitch\Core\Request;
use BarePitch\Core\Session;
use PHPUnit\Framework\TestCase;

/**
 * Tests CSRF verification behaviour at the Csrf::verify() layer.
 *
 * The application enforces CSRF on every state-changing route by calling
 * Csrf::verify($request) at the top of each controller POST method.
 * Csrf::verify() reads the '_csrf' POST field and delegates to
 * Session::verifyCsrf(). On failure it throws CsrfException.
 *
 * These tests verify:
 *   - No token in POST → CsrfException thrown
 *   - Wrong token → CsrfException thrown
 *   - Valid token → no exception
 *
 * No DB required — extends plain TestCase.
 */
class CsrfTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $_POST   = [];
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_POST   = [];
        $_SESSION = [];
        parent::tearDown();
    }

    // ----------------------------------------------------------------
    // Missing token
    // ----------------------------------------------------------------

    public function testPostWithoutCsrfTokenThrowsCsrfException(): void
    {
        // Generate a session token so the session side is initialised
        Session::csrfToken();

        // POST with no _csrf field
        $_POST = ['some_field' => 'value'];

        $request = new Request();

        $this->expectException(CsrfException::class);
        Csrf::verify($request);
    }

    public function testPostWithEmptyCsrfTokenThrowsCsrfException(): void
    {
        Session::csrfToken();

        $_POST = ['_csrf' => ''];

        $request = new Request();

        $this->expectException(CsrfException::class);
        Csrf::verify($request);
    }

    // ----------------------------------------------------------------
    // Wrong token
    // ----------------------------------------------------------------

    public function testPostWithWrongCsrfTokenThrowsCsrfException(): void
    {
        Session::csrfToken(); // sets $_SESSION['_csrf']

        $_POST = ['_csrf' => 'totally-wrong-token'];

        $request = new Request();

        $this->expectException(CsrfException::class);
        Csrf::verify($request);
    }

    public function testPostWithTamperedTokenThrowsCsrfException(): void
    {
        $token = Session::csrfToken();

        // Flip last character
        $tampered = substr($token, 0, -1) . (substr($token, -1) === 'a' ? 'b' : 'a');
        $_POST = ['_csrf' => $tampered];

        $request = new Request();

        $this->expectException(CsrfException::class);
        Csrf::verify($request);
    }

    // ----------------------------------------------------------------
    // Valid token
    // ----------------------------------------------------------------

    public function testPostWithValidCsrfTokenDoesNotThrow(): void
    {
        $token = Session::csrfToken();

        $_POST = ['_csrf' => $token, 'some_field' => 'value'];

        $request = new Request();

        // Should not throw
        Csrf::verify($request);
        $this->assertTrue(true); // explicit assertion so PHPUnit counts the test
    }

    // ----------------------------------------------------------------
    // No session token at all
    // ----------------------------------------------------------------

    public function testPostWithNoSessionTokenThrowsCsrfException(): void
    {
        // Do not call Session::csrfToken() — session has no _csrf key
        $_POST = ['_csrf' => 'any-value'];

        $request = new Request();

        $this->expectException(CsrfException::class);
        Csrf::verify($request);
    }
}
