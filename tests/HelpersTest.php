<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class HelpersTest extends TestCase
{
    public function testHEscapesHtml(): void
    {
        $this->assertSame('&lt;script&gt;', h('<script>'));
        $this->assertSame('a&amp;b', h('a&b'));
        $this->assertSame('&quot;quoted&quot;', h('"quoted"'));
        $this->assertSame('', h(null));
        $this->assertSame('0', h(0));
    }

    public function testCsrfTokenStableWithinSession(): void
    {
        $_SESSION['_csrf_token'] = null;
        unset($_SESSION['_csrf_token'], $_SESSION['_csrf_expires']);
        $t1 = csrf_token();
        $t2 = csrf_token();
        $this->assertSame($t1, $t2, 'token must be stable within a session');
        $this->assertSame(64, strlen($t1), 'token must be 32 random bytes hex-encoded');
    }

    public function testVerifyCsrfAcceptsValidToken(): void
    {
        $_SESSION['_csrf_token'] = 'abc123';
        $_SESSION['_csrf_expires'] = time() + 3600;
        $this->assertTrue(verify_csrf('abc123'));
    }

    public function testVerifyCsrfRejectsInvalidOrExpired(): void
    {
        $_SESSION['_csrf_token'] = 'abc123';
        $_SESSION['_csrf_expires'] = time() + 3600;
        $this->assertFalse(verify_csrf('wrong'));
        $this->assertFalse(verify_csrf(''));
        $this->assertFalse(verify_csrf(null));

        $_SESSION['_csrf_expires'] = time() - 10;
        $this->assertFalse(verify_csrf('abc123'), 'expired token must be rejected');
    }

    public function testSessionGetReturnsDefaultWhenMissing(): void
    {
        unset($_SESSION['nope_key']);
        $this->assertNull(session_get('nope_key'));
        $this->assertSame('fallback', session_get('nope_key', 'fallback'));
        $_SESSION['present'] = 'value';
        $this->assertSame('value', session_get('present'));
    }

    public function testAssetBuildsCacheBustedUrl(): void
    {
        $url = asset('css/app.css');
        $this->assertStringStartsWith(BASE_URL . '/assets/css/app.css?v=', $url);
        $this->assertMatchesRegularExpression('/\?v=\d+$/', $url);
        // missing file falls back to v=1
        $this->assertStringEndsWith('missing.css?v=1', asset('css/missing.css'));
    }

    public function testFlashRoundTrip(): void
    {
        set_flash('success', 'Saved!');
        $this->assertSame('Saved!', flash('success'));
        $this->assertSame('', flash('success'), 'flash must be consumed once');
    }
}
