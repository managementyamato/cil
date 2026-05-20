<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * データ読み書き関数のユニットテスト
 *
 * テスト対象: config/config.php の getInitialData(), env(),
 *           および DataSchema を介した getData() の構造保証
 *
 * 注: 2026-05-20 以降、saveData()/getData() は MySQL 必須となったため、
 * ファイル I/O ベースのバリデーションテストは廃止。
 */
class DataPersistenceTest extends TestCase
{
    // ==================== getInitialData() ====================

    public function testGetInitialDataReturnsValidStructure(): void
    {
        $data = getInitialData();

        $this->assertIsArray($data);
        $this->assertArrayHasKey('projects', $data);
        $this->assertArrayHasKey('settings', $data);
        $this->assertArrayHasKey('employees', $data);
        $this->assertArrayHasKey('customers', $data);
        $this->assertArrayHasKey('troubles', $data);
    }

    public function testGetInitialDataIsJsonEncodable(): void
    {
        $data = getInitialData();
        $json = json_encode($data, JSON_UNESCAPED_UNICODE);

        $this->assertNotFalse($json);
        $this->assertIsString($json);
    }

    public function testInitialDataRoundTripsAsJson(): void
    {
        $data = getInitialData();
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $decoded = json_decode($json, true);

        $this->assertEquals($data, $decoded);
    }

    // ==================== env()ヘルパー ====================

    public function testEnvReturnsDefaultForMissing(): void
    {
        $this->assertEquals('default_value', env('NONEXISTENT_ENV_KEY_12345', 'default_value'));
    }

    public function testEnvReturnsTrueForTrueString(): void
    {
        putenv('TEST_BOOL_VAR=true');
        $this->assertTrue(env('TEST_BOOL_VAR'));
        putenv('TEST_BOOL_VAR');
    }

    public function testEnvReturnsFalseForFalseString(): void
    {
        putenv('TEST_BOOL_VAR=false');
        $this->assertFalse(env('TEST_BOOL_VAR'));
        putenv('TEST_BOOL_VAR');
    }

    public function testEnvReturnsNullForNullString(): void
    {
        putenv('TEST_NULL_VAR=null');
        $this->assertNull(env('TEST_NULL_VAR'));
        putenv('TEST_NULL_VAR');
    }

    public function testEnvReturnsEmptyForEmptyString(): void
    {
        putenv('TEST_EMPTY_VAR=empty');
        $this->assertSame('', env('TEST_EMPTY_VAR'));
        putenv('TEST_EMPTY_VAR');
    }

    public function testEnvReturnsStringAsIs(): void
    {
        putenv('TEST_STR_VAR=hello');
        $this->assertSame('hello', env('TEST_STR_VAR'));
        putenv('TEST_STR_VAR');
    }
}
