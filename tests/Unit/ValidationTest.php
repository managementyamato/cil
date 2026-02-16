<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * バリデーション機能のユニットテスト
 */
class ValidationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        require_once dirname(__DIR__, 2) . '/functions/validation.php';
    }

    // ==================== メールアドレス検証 ====================

    public function testValidEmailReturnsTrue(): void
    {
        $this->assertTrue(validateEmail('user@example.com'));
        $this->assertTrue(validateEmail('user.name@example.co.jp'));
        $this->assertTrue(validateEmail('user+tag@example.com'));
    }

    public function testInvalidEmailReturnsFalse(): void
    {
        $this->assertFalse(validateEmail(''));
        $this->assertFalse(validateEmail('invalid'));
        $this->assertFalse(validateEmail('user@'));
        $this->assertFalse(validateEmail('@example.com'));
        $this->assertFalse(validateEmail('user@.com'));
    }

    // ==================== 電話番号検証 ====================

    public function testValidPhoneNumberReturnsTrue(): void
    {
        $this->assertTrue(validatePhone('03-1234-5678'));
        $this->assertTrue(validatePhone('090-1234-5678'));
        $this->assertTrue(validatePhone('0312345678'));
        $this->assertTrue(validatePhone('09012345678'));
    }

    public function testInvalidPhoneNumberReturnsFalse(): void
    {
        $this->assertFalse(validatePhone(''));
        $this->assertFalse(validatePhone('123'));
        $this->assertFalse(validatePhone('abcdefghijk'));
    }

    // ==================== 日付検証 ====================

    public function testValidDateReturnsTrue(): void
    {
        $this->assertTrue(validateDate('2025-01-15'));
        $this->assertTrue(validateDate('2025-12-31'));
        $this->assertTrue(validateDate('2020-02-29')); // うるう年
    }

    public function testInvalidDateReturnsFalse(): void
    {
        $this->assertFalse(validateDate(''));
        $this->assertFalse(validateDate('2025-13-01')); // 13月は無効
        $this->assertFalse(validateDate('2025-02-30')); // 2月30日は無効
        $this->assertFalse(validateDate('2021-02-29')); // うるう年でない年の2/29
        $this->assertFalse(validateDate('invalid'));
        $this->assertFalse(validateDate('01-15-2025')); // 形式違い
    }

    // ==================== 必須項目チェック ====================

    public function testRequiredFieldValidation(): void
    {
        $this->assertTrue(validateRequired('value'));
        $this->assertTrue(validateRequired('0')); // 0は有効
        $this->assertTrue(validateRequired(0)); // 数値の0も有効

        $this->assertFalse(validateRequired(''));
        $this->assertFalse(validateRequired(null));
        $this->assertFalse(validateRequired('   ')); // スペースのみは無効
    }

    // ==================== 数値検証 ====================

    public function testNumericValidation(): void
    {
        $this->assertTrue(validateNumeric('123'));
        $this->assertTrue(validateNumeric('123.45'));
        $this->assertTrue(validateNumeric('-123'));
        $this->assertTrue(validateNumeric(123));
        $this->assertTrue(validateNumeric(123.45));

        $this->assertFalse(validateNumeric('abc'));
        $this->assertFalse(validateNumeric('12a34'));
        $this->assertFalse(validateNumeric(''));
    }

    // ==================== 整数検証 ====================

    public function testIntegerValidation(): void
    {
        $this->assertTrue(validateInteger('123'));
        $this->assertTrue(validateInteger('-123'));
        $this->assertTrue(validateInteger(123));
        $this->assertTrue(validateInteger(0));

        $this->assertFalse(validateInteger('123.45'));
        $this->assertFalse(validateInteger('abc'));
        $this->assertFalse(validateInteger(''));
    }

    // ==================== 範囲検証 ====================

    public function testRangeValidation(): void
    {
        $this->assertTrue(validateRange(5, 1, 10));
        $this->assertTrue(validateRange(1, 1, 10)); // 境界値
        $this->assertTrue(validateRange(10, 1, 10)); // 境界値

        $this->assertFalse(validateRange(0, 1, 10));
        $this->assertFalse(validateRange(11, 1, 10));
    }

    // ==================== 文字列長検証 ====================

    public function testLengthValidation(): void
    {
        $this->assertTrue(validateLength('hello', 1, 10));
        $this->assertTrue(validateLength('a', 1, 10)); // 最小
        $this->assertTrue(validateLength('abcdefghij', 1, 10)); // 最大
        $this->assertTrue(validateLength('こんにちは', 1, 10)); // 日本語

        $this->assertFalse(validateLength('', 1, 10));
        $this->assertFalse(validateLength('abcdefghijk', 1, 10)); // 11文字
    }

    // ==================== URL検証 ====================

    public function testUrlValidation(): void
    {
        $this->assertTrue(validateUrl('https://example.com'));
        $this->assertTrue(validateUrl('http://example.com/path'));
        $this->assertTrue(validateUrl('https://example.com:8080/path?query=1'));

        $this->assertFalse(validateUrl(''));
        $this->assertFalse(validateUrl('example.com')); // プロトコルなし
        $this->assertFalse(validateUrl('ftp://example.com')); // http/httpsのみ
        $this->assertFalse(validateUrl('javascript:alert(1)')); // XSS
    }

    // ==================== 郵便番号検証（日本） ====================

    public function testPostalCodeValidation(): void
    {
        $this->assertTrue(validatePostalCode('123-4567'));
        $this->assertTrue(validatePostalCode('1234567'));

        $this->assertFalse(validatePostalCode(''));
        $this->assertFalse(validatePostalCode('123-456')); // 桁数不足
        $this->assertFalse(validatePostalCode('12-34567')); // 形式不正
        $this->assertFalse(validatePostalCode('abcdefg'));
    }

    // ==================== XSS対策 ====================

    public function testSanitizeHtml(): void
    {
        $this->assertEquals('&lt;script&gt;alert(1)&lt;/script&gt;', sanitizeHtml('<script>alert(1)</script>'));
        $this->assertEquals('Hello &amp; World', sanitizeHtml('Hello & World'));
        $this->assertEquals('&quot;quoted&quot;', sanitizeHtml('"quoted"'));
    }

    // ==================== バリデーションエラー収集 ====================

    public function testValidatorClass(): void
    {
        $validator = new \Validator();

        $data = [
            'email' => 'invalid-email',
            'name' => '',
            'phone' => '12345',
        ];

        $validator->required('name', $data['name'], '名前');
        $validator->email('email', $data['email'], 'メールアドレス');
        $validator->phone('phone', $data['phone'], '電話番号');

        $this->assertTrue($validator->hasErrors());
        $errors = $validator->getErrors();
        $this->assertCount(3, $errors);
    }

    public function testValidatorPasses(): void
    {
        $validator = new \Validator();

        $data = [
            'email' => 'user@example.com',
            'name' => 'テストユーザー',
            'phone' => '03-1234-5678',
        ];

        $validator->required('name', $data['name'], '名前');
        $validator->email('email', $data['email'], 'メールアドレス');
        $validator->phone('phone', $data['phone'], '電話番号');

        $this->assertFalse($validator->hasErrors());
    }
}
