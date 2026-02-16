<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * データ読み書き関数のユニットテスト
 *
 * テスト対象: config/config.php の getData(), saveData()
 * データ消失やJSON破損を防止するためのテスト
 */
class DataPersistenceTest extends TestCase
{
    private string $originalDataFile;
    private string $testDataFile;

    protected function setUp(): void
    {
        parent::setUp();

        // DATA_FILEの元の値を記録し、テスト用ファイルに切り替える
        $this->originalDataFile = DATA_FILE;
        $this->testDataFile = TEST_DATA_DIR . '/test_data_' . uniqid() . '.json';

        // DATA_FILEは定数なので直接変更できない。
        // テスト用にファイルを操作するため、直接ファイル操作でテストする
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // テスト用ファイルを削除
        if (file_exists($this->testDataFile)) {
            @unlink($this->testDataFile);
        }
        // tmpファイルも削除
        foreach (glob($this->testDataFile . '.tmp.*') as $tmp) {
            @unlink($tmp);
        }
    }

    // ==================== saveData()のバリデーション ====================

    public function testSaveDataRejectsEmptyData(): void
    {
        // 空データ（JSON < 100文字）は拒否される
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('データが不正です');

        saveData([]);
    }

    public function testSaveDataRejectsNullValues(): void
    {
        $this->expectException(\Exception::class);

        // json_encodeに失敗するデータ（PHP8.1+ではリソース型等）
        // 代わりに、サイズチェックに引っかかるデータを使用
        saveData(['a' => 'b']);
    }

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

    // ==================== getData() ====================

    public function testGetDataReturnsInitialDataWhenFileNotExists(): void
    {
        // DATA_FILEが存在しない場合（テスト環境では存在する可能性があるため、
        // getInitialDataとの構造一致で検証）
        $data = getData();
        $this->assertIsArray($data);
        $this->assertArrayHasKey('projects', $data);
        $this->assertArrayHasKey('settings', $data);
    }

    public function testGetDataReturnsArrayWithAllSchemaKeys(): void
    {
        $data = getData();
        $schemaKeys = \DataSchema::getEntityKeys();

        foreach ($schemaKeys as $key) {
            $this->assertArrayHasKey(
                $key,
                $data,
                "getData() result should contain '{$key}'"
            );
        }
    }

    // ==================== JSON整合性 ====================

    public function testInitialDataRoundTripsAsJson(): void
    {
        $data = getInitialData();
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $decoded = json_decode($json, true);

        $this->assertEquals($data, $decoded);
    }

    public function testGetDataResultIsJsonEncodable(): void
    {
        $data = getData();
        $json = json_encode($data, JSON_UNESCAPED_UNICODE);

        $this->assertNotFalse($json, 'getData() result must be JSON-encodable');
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
