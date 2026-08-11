<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class SmokeTest extends TestCase
{
    public function testPhpVersion(): void
    {
        $this->assertTrue(PHP_VERSION_ID >= 80200, 'PHP 8.2+ required');
    }

    public function testPgsRootDefined(): void
    {
        $this->assertFileExists(dirname(__DIR__) . '/src/bootstrap.php');
    }

    public function testConfigDefines(): void
    {
        $this->assertFileExists(dirname(__DIR__) . '/src/Config/config.php');
    }

    public function testDatabaseFile(): void
    {
        $this->assertFileExists(dirname(__DIR__) . '/src/Database/db.php');
    }

    public function testAuthFile(): void
    {
        $this->assertFileExists(dirname(__DIR__) . '/src/Auth/access_guard.php');
    }

    public function testNotificationFile(): void
    {
        $this->assertFileExists(dirname(__DIR__) . '/src/Notification/notification_helper.php');
    }

    public function testTemplatesNavbar(): void
    {
        $this->assertFileExists(dirname(__DIR__) . '/templates/navbar.php');
    }

    public function testTemplatesFooter(): void
    {
        $this->assertFileExists(dirname(__DIR__) . '/templates/footer.php');
    }

    public function testHttaccessExists(): void
    {
        $this->assertFileExists(dirname(__DIR__) . '/.htaccess');
    }

    public function testComposerJson(): void
    {
        $this->assertFileExists(dirname(__DIR__) . '/composer.json');
    }

    public function testPhpStanNeon(): void
    {
        $this->assertFileExists(dirname(__DIR__) . '/phpstan.neon');
    }

    public function testPhpunitXml(): void
    {
        $this->assertFileExists(dirname(__DIR__) . '/phpunit.xml');
    }

    public function testStorageLogs(): void
    {
        $this->assertFileExists(dirname(__DIR__) . '/storage/logs/.gitkeep');
    }

    public function testHFunctionExists(): void
    {
        $this->assertTrue(function_exists('h'), 'h() helper must exist');
    }

    public function testCsrfTokenFunctionExists(): void
    {
        $this->assertTrue(function_exists('csrf_token'), 'csrf_token() must exist');
    }

    public function testCsrfFieldFunctionExists(): void
    {
        $this->assertTrue(function_exists('csrf_field'), 'csrf_field() must exist');
    }

    public function testVerifyCsrfFunctionExists(): void
    {
        $this->assertTrue(function_exists('verify_csrf'), 'verify_csrf() must exist');
    }

    public function testNamespaceClassesExist(): void
    {
        $this->assertTrue(class_exists('PGS\Auth\Auth'), 'PGS\Auth\Auth must be autoloadable');
        $this->assertTrue(class_exists('PGS\Database\Database'), 'PGS\Database\Database must be autoloadable');
        $this->assertTrue(class_exists('PGS\Notification\Notifier'), 'PGS\Notification\Notifier must be autoloadable');
    }
}
