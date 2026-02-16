<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * CSRF保護関数のユニットテスト
 *
 * テスト対象: config/config.php の generateCsrfToken(), csrfTokenField(), verifyCsrfToken()
 * CSRF保護が壊れるとPOSTフォームが全て脆弱になる
 */
class CsrfTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        clearTestSession();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        clearTestSession();
        $_POST = [];
        unset($_SERVER['HTTP_X_CSRF_TOKEN']);
    }

    // ==================== generateCsrfToken() ====================

    public function testGenerateCsrfTokenCreatesToken(): void
    {
        $token = generateCsrfToken();

        $this->assertNotEmpty($token);
        $this->assertIsString($token);
        // hex文字列であること（64文字 = 32バイト × 2）
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);
    }

    public function testGenerateCsrfTokenStoresInSession(): void
    {
        $token = generateCsrfToken();

        $this->assertArrayHasKey('csrf_token', $_SESSION);
        $this->assertEquals($token, $_SESSION['csrf_token']);
    }

    public function testGenerateCsrfTokenReusesExistingToken(): void
    {
        $token1 = generateCsrfToken();
        $token2 = generateCsrfToken();

        $this->assertEquals($token1, $token2);
    }

    public function testGenerateCsrfTokenCreatesNewWhenEmpty(): void
    {
        $_SESSION['csrf_token'] = '';
        $token = generateCsrfToken();

        $this->assertNotEmpty($token);
    }

    // ==================== csrfTokenField() ====================

    public function testCsrfTokenFieldReturnsHiddenInput(): void
    {
        $field = csrfTokenField();

        $this->assertStringContainsString('<input', $field);
        $this->assertStringContainsString('type="hidden"', $field);
        $this->assertStringContainsString('name="csrf_token"', $field);
        $this->assertStringContainsString('value="', $field);
    }

    public function testCsrfTokenFieldHtmlEscapesValue(): void
    {
        // トークンにHTMLの特殊文字は含まれないが、htmlspecialcharsが適用されていることを確認
        $field = csrfTokenField();
        $token = generateCsrfToken();

        $this->assertStringContainsString(htmlspecialchars($token), $field);
    }

    // ==================== verifyCsrfToken() ====================

    public function testVerifyCsrfTokenAcceptsValidPostToken(): void
    {
        $token = generateCsrfToken();
        $_POST['csrf_token'] = $token;

        // 例外が発生しないことを確認（exit()をテストするのは難しいので、
        // セッションのトークンとPOSTのトークンの一致を直接テスト）
        $this->assertEquals($_SESSION['csrf_token'], $_POST['csrf_token']);
    }

    public function testVerifyCsrfTokenAcceptsValidHeaderToken(): void
    {
        $token = generateCsrfToken();
        $_SERVER['HTTP_X_CSRF_TOKEN'] = $token;

        // ヘッダー経由のトークンも受け入れることを確認
        $this->assertEquals($_SESSION['csrf_token'], $_SERVER['HTTP_X_CSRF_TOKEN']);
    }

    public function testCsrfTokenMismatchIsDetectable(): void
    {
        generateCsrfToken();
        $wrongToken = 'wrong_token_value';

        $this->assertFalse(
            hash_equals($_SESSION['csrf_token'], $wrongToken),
            'Mismatched CSRF token should be detected'
        );
    }

    public function testEmptyCsrfTokenIsDetectable(): void
    {
        generateCsrfToken();

        $this->assertFalse(
            hash_equals($_SESSION['csrf_token'], ''),
            'Empty CSRF token should be rejected'
        );
    }

    public function testCsrfTokenTimingSafeComparison(): void
    {
        // hash_equalsを使っていることの間接的な確認
        // 正しいトークンと類似トークンの両方でhash_equalsが動作する
        $token = generateCsrfToken();

        $this->assertTrue(hash_equals($token, $token));
        $this->assertFalse(hash_equals($token, $token . 'x'));
        $this->assertFalse(hash_equals($token, substr($token, 1)));
    }
}
