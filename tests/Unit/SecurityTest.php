<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * api-middleware.php の sanitizeInput ユニットテスト
 *
 * テスト対象: functions/api-middleware.php
 * 実装に合わせたテスト（int→キャスト方式、emailはSANITIZE方式）
 */
class SecurityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (!function_exists('sanitizeInput')) {
            require_once dirname(__DIR__, 2) . '/functions/api-middleware.php';
        }
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $_SESSION = [];
        $_GET = [];
        $_POST = [];
    }

    // ==================== 入力サニタイズ ====================

    public function testSanitizeInputString(): void
    {
        $this->assertEquals('hello', sanitizeInput('  hello  ', 'string'));
        $this->assertEquals('hello', sanitizeInput('hello', 'string'));
        $this->assertEquals('', sanitizeInput('', 'string'));
    }

    public function testSanitizeInputStringEscapesHtml(): void
    {
        $result = sanitizeInput('<script>alert(1)</script>', 'string');
        $this->assertStringContainsString('&lt;script&gt;', $result);
        $this->assertStringNotContainsString('<script>', $result);
    }

    public function testSanitizeInputInteger(): void
    {
        $this->assertSame(123, sanitizeInput('123', 'int'));
        $this->assertSame(-456, sanitizeInput('-456', 'int'));
        // 実装は(int)キャストなので、非数値は0になる
        $this->assertSame(0, sanitizeInput('abc', 'int'));
    }

    public function testSanitizeInputFloat(): void
    {
        $this->assertSame(123.45, sanitizeInput('123.45', 'float'));
        $this->assertSame(-456.78, sanitizeInput('-456.78', 'float'));
        // 非数値は0.0
        $this->assertSame(0.0, sanitizeInput('abc', 'float'));
    }

    public function testSanitizeInputBoolean(): void
    {
        $this->assertTrue(sanitizeInput('true', 'bool'));
        $this->assertTrue(sanitizeInput('1', 'bool'));
        $this->assertTrue(sanitizeInput('yes', 'bool'));
        $this->assertFalse(sanitizeInput('false', 'bool'));
        $this->assertFalse(sanitizeInput('0', 'bool'));
        $this->assertFalse(sanitizeInput('no', 'bool'));
    }

    public function testSanitizeInputEmail(): void
    {
        $this->assertEquals('user@example.com', sanitizeInput('user@example.com', 'email'));
    }

    public function testSanitizeInputDefaultTypeIsString(): void
    {
        // typeを省略するとstring扱い（HTMLエスケープされる）
        $result = sanitizeInput('<b>bold</b>');
        $this->assertStringContainsString('&lt;b&gt;', $result);
    }
}
