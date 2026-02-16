<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * セキュリティ関数のユニットテスト
 *
 * テスト対象: functions/security.php
 * パスワードポリシー、IP判定、レート制限の正確性を検証
 */
class SecurityFunctionTest extends TestCase
{
    // ==================== validatePasswordPolicy() ====================

    public function testValidPasswordPassesPolicy(): void
    {
        $policy = [
            'min_length' => 8,
            'max_length' => 128,
            'require_uppercase' => true,
            'require_lowercase' => true,
            'require_number' => true,
            'require_special' => false,
            'disallow_common' => true,
            'disallow_username' => true,
        ];

        $result = validatePasswordPolicy('StrongPass1', 'user', $policy);
        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
    }

    public function testShortPasswordFails(): void
    {
        $policy = [
            'min_length' => 8,
            'max_length' => 128,
            'require_uppercase' => false,
            'require_lowercase' => false,
            'require_number' => false,
            'require_special' => false,
            'disallow_common' => false,
            'disallow_username' => false,
        ];

        $result = validatePasswordPolicy('short', '', $policy);
        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
    }

    public function testPasswordWithoutUppercaseFails(): void
    {
        $policy = [
            'min_length' => 1,
            'max_length' => 128,
            'require_uppercase' => true,
            'require_lowercase' => false,
            'require_number' => false,
            'require_special' => false,
            'disallow_common' => false,
            'disallow_username' => false,
        ];

        $result = validatePasswordPolicy('nouppercase', '', $policy);
        $this->assertFalse($result['valid']);
    }

    public function testPasswordWithoutLowercaseFails(): void
    {
        $policy = [
            'min_length' => 1,
            'max_length' => 128,
            'require_uppercase' => false,
            'require_lowercase' => true,
            'require_number' => false,
            'require_special' => false,
            'disallow_common' => false,
            'disallow_username' => false,
        ];

        $result = validatePasswordPolicy('NOLOWERCASE', '', $policy);
        $this->assertFalse($result['valid']);
    }

    public function testPasswordWithoutNumberFails(): void
    {
        $policy = [
            'min_length' => 1,
            'max_length' => 128,
            'require_uppercase' => false,
            'require_lowercase' => false,
            'require_number' => true,
            'require_special' => false,
            'disallow_common' => false,
            'disallow_username' => false,
        ];

        $result = validatePasswordPolicy('NoNumbers', '', $policy);
        $this->assertFalse($result['valid']);
    }

    public function testPasswordWithoutSpecialCharFails(): void
    {
        $policy = [
            'min_length' => 1,
            'max_length' => 128,
            'require_uppercase' => false,
            'require_lowercase' => false,
            'require_number' => false,
            'require_special' => true,
            'disallow_common' => false,
            'disallow_username' => false,
        ];

        $result = validatePasswordPolicy('NoSpecial123', '', $policy);
        $this->assertFalse($result['valid']);
    }

    public function testCommonPasswordIsRejected(): void
    {
        $policy = [
            'min_length' => 1,
            'max_length' => 128,
            'require_uppercase' => false,
            'require_lowercase' => false,
            'require_number' => false,
            'require_special' => false,
            'disallow_common' => true,
            'disallow_username' => false,
        ];

        $result = validatePasswordPolicy('password', '', $policy);
        $this->assertFalse($result['valid']);

        $result = validatePasswordPolicy('123456', '', $policy);
        $this->assertFalse($result['valid']);
    }

    public function testPasswordContainingUsernameIsRejected(): void
    {
        $policy = [
            'min_length' => 1,
            'max_length' => 128,
            'require_uppercase' => false,
            'require_lowercase' => false,
            'require_number' => false,
            'require_special' => false,
            'disallow_common' => false,
            'disallow_username' => true,
        ];

        $result = validatePasswordPolicy('myusername123', 'username', $policy);
        $this->assertFalse($result['valid']);
    }

    // ==================== calculatePasswordStrength() ====================

    public function testEmptyPasswordHasZeroStrength(): void
    {
        $this->assertEquals(0, calculatePasswordStrength(''));
    }

    public function testSimplePasswordHasLowStrength(): void
    {
        $score = calculatePasswordStrength('123');
        $this->assertLessThan(30, $score);
    }

    public function testStrongPasswordHasHighStrength(): void
    {
        $score = calculatePasswordStrength('MyStr0ng!Pass#2024');
        $this->assertGreaterThan(60, $score);
    }

    public function testPasswordStrengthIsWithinRange(): void
    {
        // どんなパスワードでも0-100の範囲内
        $testPasswords = ['', 'a', '12345678', 'VeryLongPasswordWith!@#$%^&*()123'];
        foreach ($testPasswords as $pw) {
            $score = calculatePasswordStrength($pw);
            $this->assertGreaterThanOrEqual(0, $score);
            $this->assertLessThanOrEqual(100, $score);
        }
    }

    // ==================== ipInCidr() ====================

    public function testIpInCidrMatchesCorrectRange(): void
    {
        $this->assertTrue(ipInCidr('192.168.1.1', '192.168.1.0/24'));
        $this->assertTrue(ipInCidr('192.168.1.254', '192.168.1.0/24'));
        $this->assertTrue(ipInCidr('10.0.0.1', '10.0.0.0/8'));
    }

    public function testIpInCidrRejectsOutOfRange(): void
    {
        $this->assertFalse(ipInCidr('192.168.2.1', '192.168.1.0/24'));
        $this->assertFalse(ipInCidr('11.0.0.1', '10.0.0.0/8'));
    }

    public function testIpInCidrWithSingleIp(): void
    {
        $this->assertTrue(ipInCidr('192.168.1.1', '192.168.1.1/32'));
        $this->assertFalse(ipInCidr('192.168.1.2', '192.168.1.1/32'));
    }

    // ==================== isHttps() ====================

    public function testIsHttpsDetectsDirectHttps(): void
    {
        $originalHttps = $_SERVER['HTTPS'] ?? null;

        $_SERVER['HTTPS'] = 'on';
        $this->assertTrue(isHttps());

        // リストア
        if ($originalHttps === null) {
            unset($_SERVER['HTTPS']);
        } else {
            $_SERVER['HTTPS'] = $originalHttps;
        }
    }

    public function testIsHttpsDetectsReverseProxy(): void
    {
        $originalProto = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? null;
        $originalHttps = $_SERVER['HTTPS'] ?? null;

        unset($_SERVER['HTTPS']);
        $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
        $this->assertTrue(isHttps());

        // リストア
        if ($originalProto === null) {
            unset($_SERVER['HTTP_X_FORWARDED_PROTO']);
        } else {
            $_SERVER['HTTP_X_FORWARDED_PROTO'] = $originalProto;
        }
        if ($originalHttps !== null) {
            $_SERVER['HTTPS'] = $originalHttps;
        }
    }

    public function testIsHttpsReturnsFalseForHttp(): void
    {
        $originalHttps = $_SERVER['HTTPS'] ?? null;
        $originalProto = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? null;

        unset($_SERVER['HTTPS']);
        unset($_SERVER['HTTP_X_FORWARDED_PROTO']);
        $this->assertFalse(isHttps());

        // リストア
        if ($originalHttps !== null) {
            $_SERVER['HTTPS'] = $originalHttps;
        }
        if ($originalProto !== null) {
            $_SERVER['HTTP_X_FORWARDED_PROTO'] = $originalProto;
        }
    }

    // ==================== isIpWhitelisted() ====================

    public function testEmptyWhitelistAllowsAll(): void
    {
        // ホワイトリストが空の場合は全て許可
        // （config/security-config.jsonが存在しない場合）
        // この動作は getIpWhitelist() の戻り値に依存
        $whitelist = getIpWhitelist();
        if (empty($whitelist)) {
            $this->assertTrue(isIpWhitelisted('1.2.3.4'));
        } else {
            $this->markTestSkipped('IP whitelist is configured');
        }
    }
}
