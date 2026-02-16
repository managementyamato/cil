<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * 暗号化モジュールのユニットテスト
 *
 * テスト対象: functions/encryption.php
 * AES-256-GCM 暗号化・復号・フィールド暗号化・マスク関数をテスト
 */
class EncryptionTest extends TestCase
{
    private string $testKeyFile;

    protected function setUp(): void
    {
        parent::setUp();

        // テスト用の暗号化鍵ファイルを作成
        $this->testKeyFile = dirname(__DIR__, 2) . '/config/encryption.key';

        // 既存の鍵ファイルがなければテスト用を作成
        if (!file_exists($this->testKeyFile)) {
            if (!function_exists('generateEncryptionKey')) {
                require_once dirname(__DIR__, 2) . '/functions/encryption.php';
            }
            file_put_contents($this->testKeyFile, generateEncryptionKey());
        }

        if (!function_exists('encryptValue')) {
            require_once dirname(__DIR__, 2) . '/functions/encryption.php';
        }
    }

    // ========================================
    // encryptValue / decryptValue テスト
    // ========================================

    public function testEncryptDecryptRoundTrip(): void
    {
        $plaintext = 'テスト電話番号 03-1234-5678';
        $encrypted = encryptValue($plaintext);

        // enc: プレフィックスが付いていること
        $this->assertStringStartsWith('enc:', $encrypted);
        // 元のテキストと異なること
        $this->assertNotEquals($plaintext, $encrypted);

        // 復号して元に戻ること
        $decrypted = decryptValue($encrypted);
        $this->assertEquals($plaintext, $decrypted);
    }

    public function testEncryptValueReturnsNullForNull(): void
    {
        $this->assertNull(encryptValue(null));
    }

    public function testEncryptValueReturnsEmptyForEmpty(): void
    {
        $this->assertEquals('', encryptValue(''));
    }

    public function testDecryptValueReturnsNullForNull(): void
    {
        $this->assertNull(decryptValue(null));
    }

    public function testDecryptValueReturnsEmptyForEmpty(): void
    {
        $this->assertEquals('', decryptValue(''));
    }

    public function testDecryptValueReturnsPlaintextAsIs(): void
    {
        // enc: プレフィックスがない場合はそのまま返す（後方互換）
        $plaintext = '普通のテキスト';
        $this->assertEquals($plaintext, decryptValue($plaintext));
    }

    public function testNoDoubleEncryption(): void
    {
        $plaintext = 'test@example.com';
        $encrypted = encryptValue($plaintext);

        // 既に暗号化済みのデータを再暗号化しようとしても変わらない
        $doubleEncrypted = encryptValue($encrypted);
        $this->assertEquals($encrypted, $doubleEncrypted);

        // 復号は正常に動作する
        $decrypted = decryptValue($doubleEncrypted);
        $this->assertEquals($plaintext, $decrypted);
    }

    public function testEachEncryptionProducesDifferentResult(): void
    {
        // 同じ平文でも毎回異なる暗号文を生成する（IVがランダム）
        $plaintext = '同じテキスト';
        $encrypted1 = encryptValue($plaintext);
        $encrypted2 = encryptValue($plaintext);

        $this->assertNotEquals($encrypted1, $encrypted2);

        // どちらも正しく復号できる
        $this->assertEquals($plaintext, decryptValue($encrypted1));
        $this->assertEquals($plaintext, decryptValue($encrypted2));
    }

    public function testDecryptInvalidDataReturnsOriginal(): void
    {
        // 不正なenc:データは元の値を返す
        $invalidData = 'enc:notvalidbase64!!!';
        $result = decryptValue($invalidData);
        $this->assertEquals($invalidData, $result);
    }

    public function testDecryptShortDataReturnsOriginal(): void
    {
        // 短すぎるenc:データは元の値を返す
        $shortData = 'enc:' . base64_encode('short');
        $result = decryptValue($shortData);
        $this->assertEquals($shortData, $result);
    }

    // ========================================
    // encryptFields / decryptFields テスト
    // ========================================

    public function testEncryptFields(): void
    {
        $record = [
            'id' => 'c_123',
            'companyName' => 'テスト株式会社',
            'phone' => '03-1234-5678',
            'email' => 'test@example.com',
            'address' => '東京都千代田区1-1-1',
            'notes' => 'メモ',
        ];

        $encrypted = encryptFields($record, ['phone', 'email', 'address']);

        // 暗号化対象フィールドはenc:で始まる
        $this->assertStringStartsWith('enc:', $encrypted['phone']);
        $this->assertStringStartsWith('enc:', $encrypted['email']);
        $this->assertStringStartsWith('enc:', $encrypted['address']);

        // 暗号化対象外フィールドはそのまま
        $this->assertEquals('c_123', $encrypted['id']);
        $this->assertEquals('テスト株式会社', $encrypted['companyName']);
        $this->assertEquals('メモ', $encrypted['notes']);
    }

    public function testDecryptFields(): void
    {
        $record = [
            'id' => 'c_123',
            'phone' => '03-1234-5678',
            'email' => 'test@example.com',
        ];

        // 暗号化してから復号
        $encrypted = encryptFields($record, ['phone', 'email']);
        $decrypted = decryptFields($encrypted, ['phone', 'email']);

        $this->assertEquals('03-1234-5678', $decrypted['phone']);
        $this->assertEquals('test@example.com', $decrypted['email']);
        $this->assertEquals('c_123', $decrypted['id']);
    }

    public function testEncryptFieldsSkipsEmpty(): void
    {
        $record = [
            'phone' => '',
            'email' => null,
            'address' => '東京都',
        ];

        $encrypted = encryptFields($record, ['phone', 'email', 'address']);

        // 空フィールドはそのまま
        $this->assertEquals('', $encrypted['phone']);
        $this->assertNull($encrypted['email']);

        // 値があるフィールドは暗号化
        $this->assertStringStartsWith('enc:', $encrypted['address']);
    }

    public function testEncryptFieldsSkipsMissingFields(): void
    {
        $record = ['phone' => '03-1234-5678'];

        // 存在しないフィールドを指定してもエラーにならない
        $encrypted = encryptFields($record, ['phone', 'email', 'address']);

        $this->assertStringStartsWith('enc:', $encrypted['phone']);
        $this->assertArrayNotHasKey('email', $encrypted);
    }

    // ========================================
    // encryptCustomerData / decryptCustomerData テスト
    // ========================================

    public function testEncryptDecryptCustomerData(): void
    {
        $data = [
            'customers' => [
                [
                    'id' => 'c_1',
                    'companyName' => 'テスト社',
                    'phone' => '03-1111-2222',
                    'email' => 'a@test.com',
                    'address' => '東京都',
                    'branches' => [
                        [
                            'id' => 'br_1',
                            'name' => '大阪支店',
                            'phone' => '06-3333-4444',
                            'address' => '大阪府',
                        ]
                    ]
                ]
            ],
            'assignees' => [
                [
                    'id' => 'a_1',
                    'name' => '山田太郎',
                    'phone' => '090-1234-5678',
                    'email' => 'yamada@test.com',
                ]
            ],
            'partners' => [
                [
                    'id' => 'pt_1',
                    'companyName' => 'パートナー社',
                    'phone' => '03-5555-6666',
                    'email' => 'partner@test.com',
                    'address' => '神奈川県',
                ]
            ],
            'projects' => [
                ['id' => 'p_1', 'name' => 'プロジェクト1']
            ],
        ];

        // 暗号化
        encryptCustomerData($data);

        // 顧客のフィールドが暗号化されている
        $this->assertStringStartsWith('enc:', $data['customers'][0]['phone']);
        $this->assertStringStartsWith('enc:', $data['customers'][0]['email']);
        $this->assertStringStartsWith('enc:', $data['customers'][0]['address']);
        $this->assertEquals('テスト社', $data['customers'][0]['companyName']); // 非暗号化

        // 営業所のフィールドが暗号化されている
        $this->assertStringStartsWith('enc:', $data['customers'][0]['branches'][0]['phone']);
        $this->assertStringStartsWith('enc:', $data['customers'][0]['branches'][0]['address']);
        $this->assertEquals('大阪支店', $data['customers'][0]['branches'][0]['name']); // 非暗号化

        // 担当者のフィールドが暗号化されている
        $this->assertStringStartsWith('enc:', $data['assignees'][0]['phone']);
        $this->assertStringStartsWith('enc:', $data['assignees'][0]['email']);
        $this->assertEquals('山田太郎', $data['assignees'][0]['name']); // 非暗号化

        // パートナーのフィールドが暗号化されている
        $this->assertStringStartsWith('enc:', $data['partners'][0]['phone']);
        $this->assertStringStartsWith('enc:', $data['partners'][0]['email']);
        $this->assertStringStartsWith('enc:', $data['partners'][0]['address']);
        $this->assertEquals('パートナー社', $data['partners'][0]['companyName']); // 非暗号化

        // 関係ないデータは影響を受けない
        $this->assertEquals('p_1', $data['projects'][0]['id']);

        // 復号
        decryptCustomerData($data);

        // 元に戻っている
        $this->assertEquals('03-1111-2222', $data['customers'][0]['phone']);
        $this->assertEquals('a@test.com', $data['customers'][0]['email']);
        $this->assertEquals('東京都', $data['customers'][0]['address']);
        $this->assertEquals('06-3333-4444', $data['customers'][0]['branches'][0]['phone']);
        $this->assertEquals('大阪府', $data['customers'][0]['branches'][0]['address']);
        $this->assertEquals('090-1234-5678', $data['assignees'][0]['phone']);
        $this->assertEquals('yamada@test.com', $data['assignees'][0]['email']);
        $this->assertEquals('03-5555-6666', $data['partners'][0]['phone']);
        $this->assertEquals('partner@test.com', $data['partners'][0]['email']);
        $this->assertEquals('神奈川県', $data['partners'][0]['address']);
    }

    public function testEncryptCustomerDataHandlesEmptyArrays(): void
    {
        $data = [
            'customers' => [],
            'assignees' => [],
            'partners' => [],
        ];

        // エラーにならないこと
        encryptCustomerData($data);
        decryptCustomerData($data);

        $this->assertEmpty($data['customers']);
    }

    public function testEncryptCustomerDataHandlesMissingKeys(): void
    {
        $data = ['projects' => [['id' => 'p_1']]];

        // customers/assignees/partners がなくてもエラーにならない
        encryptCustomerData($data);
        decryptCustomerData($data);

        $this->assertEquals('p_1', $data['projects'][0]['id']);
    }

    // ========================================
    // マスク関数テスト
    // ========================================

    public function testMaskPhone(): void
    {
        $this->assertEquals('03-****-5678', maskPhone('03-1234-5678'));
        $this->assertEquals('090-****-5678', maskPhone('090-1234-5678'));
        $this->assertEquals('', maskPhone(''));
    }

    public function testMaskPhoneWithoutHyphen(): void
    {
        $result = maskPhone('0312345678');
        $this->assertNotEquals('0312345678', $result);
        $this->assertStringContainsString('*', $result);
    }

    public function testMaskEmail(): void
    {
        $this->assertEquals('u**r@example.com', maskEmail('user@example.com'));
        $this->assertEquals('a*@example.com', maskEmail('ab@example.com'));
        $this->assertEquals('', maskEmail(''));
    }

    // ========================================
    // 鍵生成テスト
    // ========================================

    public function testGenerateEncryptionKey(): void
    {
        $key = generateEncryptionKey();

        // Base64エンコードされた文字列
        $this->assertIsString($key);

        // デコードすると32バイト
        $decoded = base64_decode($key, true);
        $this->assertNotFalse($decoded);
        $this->assertEquals(32, strlen($decoded));
    }

    public function testGenerateEncryptionKeyIsRandom(): void
    {
        $key1 = generateEncryptionKey();
        $key2 = generateEncryptionKey();

        // 毎回異なる鍵が生成される
        $this->assertNotEquals($key1, $key2);
    }

    // ========================================
    // 定数テスト
    // ========================================

    public function testEncryptFieldConstants(): void
    {
        $this->assertEquals(['phone', 'email', 'address'], CUSTOMER_ENCRYPT_FIELDS);
        $this->assertEquals(['phone', 'address'], BRANCH_ENCRYPT_FIELDS);
        $this->assertEquals(['phone', 'email'], ASSIGNEE_ENCRYPT_FIELDS);
        $this->assertEquals(['phone', 'email', 'address'], PARTNER_ENCRYPT_FIELDS);
    }
}
